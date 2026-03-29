<?php

namespace App\Services;

use App\Models\VillageDailyStat;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VillageStatsService
{
    private const PER_PAGE = 20;

    private const MAX_RESULTS = 100;

    private const MAX_PAGES = 5;

    /**
     * Najbližších MAX_RESULTS dedín k bodu (x, y), alebo zoznam bez súradníc zoradený podľa sort_by (predvolene populácia dediny).
     */
    public function search(
        int $serverId,
        ?int $x,
        ?int $y,
        int $page = 1,
        string $accountFilter = '',
        string $villageFilter = '',
        bool $excludeMyAccount = false,
        string $myAccountName = '',
        string $allianceFilter = '',
        ?int $tribeId = null,
        ?int $playerId = null,
        ?int $allianceId = null,
        string $sortBy = 'population',
        string $sortDir = 'desc',
    ): array {
        $page = max(1, min($page, self::MAX_PAGES));
        $hasCoords = $x !== null && $y !== null;

        $sortBy = strtolower($sortBy);
        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['distance', 'population', 'account', 'village', 'alliance'];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = $hasCoords ? 'distance' : 'population';
        }
        if (! $hasCoords && $sortBy === 'distance') {
            $sortBy = 'population';
            $sortDir = 'desc';
        }

        $base = $this->filteredVillageQuery(
            $serverId,
            $accountFilter,
            $villageFilter,
            $excludeMyAccount,
            $myAccountName,
            $allianceFilter,
            $tribeId,
            $playerId,
            $allianceId,
        );

        $matchCount = (int) (clone $base)->count();

        $effectiveTotal = min(self::MAX_RESULTS, $matchCount);
        $lastPage = $effectiveTotal > 0 ? (int) min(self::MAX_PAGES, (int) ceil($effectiveTotal / self::PER_PAGE)) : 1;

        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $offset = ($page - 1) * self::PER_PAGE;

        $ranked = (clone $base)
            ->select([
                'v.id',
                'v.x',
                'v.y',
                'v.name as village_name',
                'v.tribe',
                'v.days_without_change',
                'v.player_id',
                'v.alliance_id',
                'p.name as player_name',
                'a.tag as alliance_tag',
            ]);

        $needsLatestPopJoin = ! $hasCoords || $sortBy === 'population';

        if ($hasCoords) {
            $ranked->selectRaw('SQRT(POW(v.x - ?, 2) + POW(v.y - ?, 2)) as distance', [$x, $y]);
        } else {
            $ranked->selectRaw('NULL as distance');
        }

        if ($needsLatestPopJoin) {
            $latestDates = DB::table('village_daily_stats')
                ->select('village_id', DB::raw('MAX(snapshot_date) as max_date'))
                ->groupBy('village_id');

            $ranked->leftJoinSub($latestDates, 'vds_mx', function ($join): void {
                $join->on('vds_mx.village_id', '=', 'v.id');
            })
                ->leftJoin('village_daily_stats as vds_l', function ($join): void {
                    $join->on('vds_l.village_id', '=', 'v.id')
                        ->on('vds_l.snapshot_date', '=', 'vds_mx.max_date');
                });
        }

        $this->applyVillageSortOrder($ranked, $sortBy, $sortDir, $hasCoords);

        $ranked->limit(self::MAX_RESULTS);

        $rows = DB::query()
            ->fromSub($ranked, 't')
            ->offset($offset)
            ->limit(self::PER_PAGE)
            ->get();

        if ($rows->isEmpty()) {
            return $this->emptyPayload($page, $effectiveTotal, $lastPage, $hasCoords);
        }

        $villageIds = $rows->pluck('id')->all();
        $playerIds = $rows->pluck('player_id')->unique()->all();

        $latestPops = $this->latestPopulationsByVillageId($villageIds);
        $playerAgg = $this->playerAggregates($serverId, $playerIds);

        $dateColumns = $this->dateColumns();
        $changes = $this->populationChangesForVillages($villageIds, $dateColumns);

        $tribes = config('travian.tribes', []);

        $mapped = $rows->map(function ($row) use ($latestPops, $playerAgg, $changes, $dateColumns, $tribes, $hasCoords) {
            $vid = (int) $row->id;
            $pid = (int) $row->player_id;
            $pop = $latestPops->get($vid);
            $population = $pop !== null ? (int) $pop->population : null;

            $agg = $playerAgg->get($pid);

            $daily = [];
            foreach ($dateColumns as $d) {
                $daily[$d] = $changes->get($vid)?->get($d)?->population_change;
            }

            $aid = $row->alliance_id !== null ? (int) $row->alliance_id : null;

            return [
                'village_id' => $vid,
                'player_id' => $pid,
                'alliance_id' => $aid,
                'distance' => $hasCoords ? round((float) $row->distance, 2) : null,
                'account' => [
                    'name' => $row->player_name,
                    'total_population' => $agg->total_population ?? 0,
                    'village_count' => (int) ($agg->village_count ?? 0),
                ],
                'village' => [
                    'name' => $row->village_name,
                    'x' => (int) $row->x,
                    'y' => (int) $row->y,
                    'population' => $population,
                    'tribe' => (int) $row->tribe,
                    'tribe_label' => $tribes[(int) $row->tribe] ?? '—',
                    'days_without_change' => (int) $row->days_without_change,
                ],
                'alliance' => [
                    'tag' => $this->normalizeAllianceTag($row->alliance_tag ?? null),
                ],
                'daily_changes' => $daily,
                'actions' => null,
            ];
        })->values()->all();

        return [
            'date_columns' => $dateColumns,
            'rows' => $mapped,
            'meta' => [
                'current_page' => $page,
                'per_page' => self::PER_PAGE,
                'total' => $effectiveTotal,
                'last_page' => $lastPage,
                'has_coordinates' => $hasCoords,
            ],
        ];
    }

    private function applyVillageSortOrder(Builder $ranked, string $sortBy, string $sortDir, bool $hasCoords): void
    {
        if ($sortBy === 'distance' && $hasCoords) {
            $ranked->orderBy('distance', $sortDir)->orderBy('v.id');

            return;
        }

        if ($sortBy === 'population') {
            $ranked->orderByRaw('COALESCE(vds_l.population, 0) '.$sortDir)->orderBy('v.id');

            return;
        }

        if ($sortBy === 'account') {
            $ranked->orderBy('p.name', $sortDir)->orderBy('v.id');

            return;
        }

        if ($sortBy === 'village') {
            $ranked->orderBy('v.name', $sortDir)->orderBy('v.id');

            return;
        }

        if ($sortBy === 'alliance') {
            $ranked->orderByRaw('LOWER(COALESCE(a.tag, \'\')) '.$sortDir)->orderBy('v.id');

            return;
        }

        if ($hasCoords) {
            $ranked->orderBy('distance', 'asc')->orderBy('v.id');
        } else {
            $ranked->orderByRaw('COALESCE(vds_l.population, 0) DESC')->orderBy('v.id');
        }
    }

    private function filteredVillageQuery(
        int $serverId,
        string $accountFilter,
        string $villageFilter,
        bool $excludeMyAccount,
        string $myAccountName,
        string $allianceFilter = '',
        ?int $tribeId = null,
        ?int $playerId = null,
        ?int $allianceId = null,
    ): Builder {
        $q = DB::table('villages as v')
            ->join('players as p', 'p.id', '=', 'v.player_id')
            ->leftJoin('alliances as a', 'a.id', '=', 'v.alliance_id')
            ->where('v.server_id', $serverId);

        if ($playerId !== null) {
            $q->where('v.player_id', $playerId);
        }

        if ($allianceId !== null) {
            $q->where('v.alliance_id', $allianceId);
        }

        $this->applyAccountNameFilter($q, $accountFilter);
        $this->applyVillageFilter($q, $villageFilter);
        $this->applyExcludeMyAccount($q, $excludeMyAccount, $myAccountName);
        $this->applyAllianceTagFilter($q, $allianceFilter);
        $this->applyTribeFilter($q, $tribeId);

        return $q;
    }

    private function normalizeAllianceTag(mixed $tag): ?string
    {
        if ($tag === null) {
            return null;
        }
        $s = trim((string) $tag);

        return $s === '' ? null : $s;
    }

    private function applyAccountNameFilter(Builder $q, string $accountFilter): void
    {
        $t = trim($accountFilter);
        if ($t === '') {
            return;
        }

        $like = '%'.$this->escapeLike(Str::lower($t)).'%';
        $q->whereRaw('LOWER(p.name) LIKE ?', [$like]);
    }

    private function applyVillageFilter(Builder $q, string $villageFilter): void
    {
        $t = trim($villageFilter);
        if ($t === '') {
            return;
        }

        if (preg_match('/^\s*(-?\d+)\s*\|\s*(-?\d+)\s*$/', $t, $m)) {
            $q->where('v.x', (int) $m[1])->where('v.y', (int) $m[2]);

            return;
        }

        $like = '%'.$this->escapeLike(Str::lower($t)).'%';
        $q->whereRaw('LOWER(v.name) LIKE ?', [$like]);
    }

    private function applyExcludeMyAccount(Builder $q, bool $exclude, string $myAccountName): void
    {
        if (! $exclude) {
            return;
        }

        $name = trim($myAccountName);
        if ($name === '') {
            return;
        }

        $q->whereRaw('LOWER(TRIM(p.name)) != LOWER(?)', [$name]);
    }

    private function applyAllianceTagFilter(Builder $q, string $allianceFilter): void
    {
        $t = trim($allianceFilter);
        if ($t === '') {
            return;
        }

        $like = '%'.$this->escapeLike(Str::lower($t)).'%';
        $q->whereRaw('LOWER(COALESCE(a.tag, \'\')) LIKE ?', [$like]);
    }

    private function applyTribeFilter(Builder $q, ?int $tribeId): void
    {
        if ($tribeId === null) {
            return;
        }

        $q->where('v.tribe', $tribeId);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @return list<string>
     */
    private function dateColumns(): array
    {
        $tz = (string) config('travian.report_timezone', 'Europe/Bratislava');
        $end = Carbon::now($tz)->startOfDay();
        $dates = [];
        for ($i = 6; $i >= 0; $i--) {
            $dates[] = $end->copy()->subDays($i)->toDateString();
        }

        return array_reverse($dates);
    }

    /**
     * @param  list<int>  $villageIds
     * @param  list<string>  $dateColumns
     */
    private function populationChangesForVillages(array $villageIds, array $dateColumns): Collection
    {
        if ($villageIds === []) {
            return collect();
        }

        $stats = VillageDailyStat::query()
            ->whereIn('village_id', $villageIds)
            ->whereIn('snapshot_date', $dateColumns)
            ->get(['village_id', 'snapshot_date', 'population_change']);

        return $stats->groupBy('village_id')->map(function (Collection $group) {
            return $group->keyBy(function (VillageDailyStat $s) {
                return $s->snapshot_date->toDateString();
            });
        });
    }

    /**
     * @param  list<int>  $villageIds
     * @return Collection<int, object{population: int}>
     */
    private function latestPopulationsByVillageId(array $villageIds): Collection
    {
        if ($villageIds === []) {
            return collect();
        }

        $latest = VillageDailyStat::query()
            ->select('village_id', DB::raw('MAX(snapshot_date) as max_date'))
            ->whereIn('village_id', $villageIds)
            ->groupBy('village_id');

        return DB::query()
            ->from('village_daily_stats as vds')
            ->joinSub($latest, 'mx', function ($join) {
                $join->on('vds.village_id', '=', 'mx.village_id')
                    ->on('vds.snapshot_date', '=', 'mx.max_date');
            })
            ->get(['vds.village_id', 'vds.population'])
            ->keyBy('village_id');
    }

    /**
     * @param  list<int>  $playerIds
     * @return Collection<int, object{total_population: int, village_count: int}>
     */
    private function playerAggregates(int $serverId, array $playerIds): Collection
    {
        if ($playerIds === []) {
            return collect();
        }

        $villageIds = DB::table('villages')
            ->where('server_id', $serverId)
            ->whereIn('player_id', $playerIds)
            ->pluck('id')
            ->all();

        if ($villageIds === []) {
            return collect();
        }

        $latest = VillageDailyStat::query()
            ->select('village_id', DB::raw('MAX(snapshot_date) as max_date'))
            ->whereIn('village_id', $villageIds)
            ->groupBy('village_id');

        $pops = DB::query()
            ->from('village_daily_stats as vds')
            ->joinSub($latest, 'mx', function ($join) {
                $join->on('vds.village_id', '=', 'mx.village_id')
                    ->on('vds.snapshot_date', '=', 'mx.max_date');
            })
            ->join('villages as v', 'v.id', '=', 'vds.village_id')
            ->where('v.server_id', $serverId)
            ->get(['v.player_id', 'vds.population']);

        $byPlayer = $pops->groupBy('player_id');

        return $byPlayer->map(function (Collection $group, $playerId) use ($serverId) {
            return (object) [
                'total_population' => (int) $group->sum('population'),
                'village_count' => (int) DB::table('villages')
                    ->where('server_id', $serverId)
                    ->where('player_id', $playerId)
                    ->count(),
            ];
        });
    }

    private function emptyPayload(int $page, int $effectiveTotal, int $lastPage, bool $hasCoordinates = false): array
    {
        return [
            'date_columns' => $this->dateColumns(),
            'rows' => [],
            'meta' => [
                'current_page' => $page,
                'per_page' => self::PER_PAGE,
                'total' => $effectiveTotal,
                'last_page' => max(1, $lastPage),
                'has_coordinates' => $hasCoordinates,
            ],
        ];
    }
}
