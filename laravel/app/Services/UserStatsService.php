<?php

namespace App\Services;

use App\Models\PlayerDailyStat;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserStatsService
{
    private const PER_PAGE = 20;

    private const MAX_RESULTS = 100;

    private const MAX_PAGES = 5;

    /**
     * @return array{
     *     date_columns: list<string>,
     *     rows: list<array<string, mixed>>,
     *     meta: array{
     *         current_page: int,
     *         per_page: int,
     *         total: int,
     *         last_page: int,
     *         has_coordinates: bool,
     *     },
     * }
     */
    public function search(
        int $serverId,
        ?int $x,
        ?int $y,
        int $page = 1,
        string $accountFilter = '',
        string $allianceFilter = '',
        string $sortBy = 'population',
        string $sortDir = 'desc',
        ?int $playerId = null,
        ?int $allianceId = null,
    ): array {
        $page = max(1, min($page, self::MAX_PAGES));
        $hasCoords = $x !== null && $y !== null;

        if (! $hasCoords) {
            if ($sortBy === 'distance') {
                $sortBy = 'population';
                $sortDir = 'desc';
            }
        }

        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        $latestDate = $this->latestSnapshotDateForServer($serverId);
        if ($latestDate === null) {
            return $this->emptyPayload($page, $hasCoords);
        }

        $filtered = $this->filteredPlayersQuery($serverId, $accountFilter, $allianceFilter, $playerId, $allianceId);
        $matchCount = (int) (clone $filtered)->count();

        if ($matchCount === 0) {
            return $this->emptyPayload($page, $hasCoords, 0, 1);
        }

        $effectiveTotal = min(self::MAX_RESULTS, $matchCount);
        $lastPage = $effectiveTotal > 0 ? (int) min(self::MAX_PAGES, (int) ceil($effectiveTotal / self::PER_PAGE)) : 1;

        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $offset = ($page - 1) * self::PER_PAGE;

        $popByPlayer = $this->subqueryPopulationSumByPlayer($serverId);
        $villByPlayer = $this->subqueryVillageCountByPlayer($serverId);

        $inner = DB::query()
            ->fromSub($filtered->select('p.id', 'p.name'), 'fp')
            ->join('players as p', 'p.id', '=', 'fp.id')
            ->leftJoinSub($popByPlayer, 'pop', 'pop.player_id', '=', 'p.id')
            ->leftJoinSub($villByPlayer, 'vc', 'vc.player_id', '=', 'p.id')
            ->select([
                'p.id',
                'p.name',
                'p.external_id as player_external_id',
                DB::raw('COALESCE(pop.total_population, 0) as total_population'),
                DB::raw('COALESCE(vc.village_count, 0) as village_count'),
            ]);

        if ($hasCoords) {
            $distByPlayer = $this->subqueryMinDistanceByPlayer($serverId, $x, $y);
            $inner->joinSub($distByPlayer, 'dist', 'dist.player_id', '=', 'p.id')
                ->addSelect(DB::raw('dist.distance as distance'));
        } else {
            $inner->addSelect(DB::raw('NULL as distance'));
        }

        $this->applyRankingOrder($inner, $sortBy, $sortDir, $hasCoords);
        $inner->orderBy('p.id');

        $ranked = DB::query()
            ->fromSub($inner->limit(self::MAX_RESULTS), 't');

        $rows = (clone $ranked)
            ->offset($offset)
            ->limit(self::PER_PAGE)
            ->get();

        if ($rows->isEmpty()) {
            return $this->emptyPayload($page, $hasCoords, $effectiveTotal, $lastPage);
        }

        $playerIds = $rows->pluck('id')->all();
        $dateColumns = $this->dateColumns();
        $changes = $this->populationChangesForPlayers($playerIds, $dateColumns);
        $allianceInfo = $this->allianceInfoByPlayer($serverId, $playerIds);

        $mapped = $rows->map(function ($row) use ($changes, $dateColumns, $allianceInfo, $hasCoords) {
            $pid = (int) $row->id;
            $daily = [];
            foreach ($dateColumns as $d) {
                $daily[$d] = $changes->get($pid)?->get($d)?->population_change;
            }

            $dist = $row->distance !== null ? round((float) $row->distance, 2) : null;
            $aInfo = $allianceInfo->get($pid);

            return [
                'player_id' => $pid,
                'player_external_id' => (int) $row->player_external_id,
                'distance' => $hasCoords ? $dist : null,
                'village_count' => (int) $row->village_count,
                'account' => [
                    'name' => $row->name,
                    'total_population' => (int) $row->total_population,
                ],
                'alliance' => [
                    'tag' => $aInfo !== null ? ($aInfo['tag'] ?? null) : null,
                    'alliance_id' => $aInfo !== null ? ($aInfo['alliance_id'] ?? null) : null,
                ],
                'daily_changes' => $daily,
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

    private function filteredPlayersQuery(
        int $serverId,
        string $accountFilter,
        string $allianceFilter,
        ?int $playerId,
        ?int $allianceId,
    ): Builder {
        $q = DB::table('players as p')
            ->where('p.server_id', $serverId)
            ->whereExists(function ($sub) use ($serverId): void {
                $sub->from('villages as vx')
                    ->whereColumn('vx.player_id', 'p.id')
                    ->where('vx.server_id', $serverId);
            });

        if ($playerId !== null) {
            $q->where('p.id', $playerId);
        }

        if ($allianceId !== null) {
            $q->whereExists(function ($sub) use ($serverId, $allianceId): void {
                $sub->from('villages as v')
                    ->whereColumn('v.player_id', 'p.id')
                    ->where('v.server_id', $serverId)
                    ->where('v.alliance_id', $allianceId);
            });
        }

        $this->applyAccountNameFilter($q, $accountFilter);
        $this->applyAllianceTagFilter($q, $serverId, $allianceFilter);

        return $q;
    }

    private function applyRankingOrder(Builder $inner, string $sortBy, string $sortDir, bool $hasCoords): void
    {
        if ($sortBy === 'distance' && $hasCoords) {
            $inner->orderBy('dist.distance', $sortDir);

            return;
        }

        if ($sortBy === 'account') {
            $inner->orderBy('p.name', $sortDir);

            return;
        }

        if ($sortBy === 'villages') {
            $inner->orderByRaw('COALESCE(vc.village_count, 0) '.$sortDir);

            return;
        }

        $inner->orderByRaw('COALESCE(pop.total_population, 0) '.$sortDir);
    }

    private function latestSnapshotDateForServer(int $serverId): ?string
    {
        $d = DB::table('village_daily_stats as vds')
            ->join('villages as v', 'v.id', '=', 'vds.village_id')
            ->where('v.server_id', $serverId)
            ->max('vds.snapshot_date');

        return $d !== null ? (string) $d : null;
    }

    private function subqueryPopulationSumByPlayer(int $serverId): Builder
    {
        $latest = DB::table('village_daily_stats')
            ->select('village_id', DB::raw('MAX(snapshot_date) as max_date'))
            ->groupBy('village_id');

        return DB::query()
            ->from('village_daily_stats as vds')
            ->joinSub($latest, 'mx', function ($join): void {
                $join->on('vds.village_id', '=', 'mx.village_id')
                    ->on('vds.snapshot_date', '=', 'mx.max_date');
            })
            ->join('villages as v', 'v.id', '=', 'vds.village_id')
            ->where('v.server_id', $serverId)
            ->groupBy('v.player_id')
            ->selectRaw('v.player_id as player_id, SUM(vds.population) as total_population');
    }

    private function subqueryVillageCountByPlayer(int $serverId): Builder
    {
        return DB::table('villages')
            ->where('server_id', $serverId)
            ->groupBy('player_id')
            ->selectRaw('player_id, COUNT(*) as village_count');
    }

    private function subqueryMinDistanceByPlayer(int $serverId, int $x, int $y): Builder
    {
        return DB::table('villages as v')
            ->where('v.server_id', $serverId)
            ->groupBy('v.player_id')
            ->select('v.player_id')
            ->selectRaw('MIN(SQRT(POW(v.x - ?, 2) + POW(v.y - ?, 2))) as distance', [$x, $y]);
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

    private function applyAllianceTagFilter(Builder $q, int $serverId, string $allianceFilter): void
    {
        $t = trim($allianceFilter);
        if ($t === '') {
            return;
        }

        $like = '%'.$this->escapeLike(Str::lower($t)).'%';
        $q->whereExists(function ($sub) use ($serverId, $like): void {
            $sub->from('villages as v')
                ->leftJoin('alliances as a', 'a.id', '=', 'v.alliance_id')
                ->whereColumn('v.player_id', 'p.id')
                ->where('v.server_id', $serverId)
                ->whereRaw('LOWER(COALESCE(a.tag, \'\')) LIKE ?', [$like]);
        });
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Tag a id aliancie z dediny s najvyššou populáciou (podľa posledného snapshotu dediny).
     *
     * @param  list<int>  $playerIds
     * @return Collection<int, array{tag: string|null, alliance_id: int|null}>
     */
    private function allianceInfoByPlayer(int $serverId, array $playerIds): Collection
    {
        if ($playerIds === []) {
            return collect();
        }

        $latest = DB::table('village_daily_stats')
            ->select('village_id', DB::raw('MAX(snapshot_date) as max_date'))
            ->groupBy('village_id');

        $rows = DB::query()
            ->from('village_daily_stats as vds')
            ->joinSub($latest, 'mx', function ($join): void {
                $join->on('vds.village_id', '=', 'mx.village_id')
                    ->on('vds.snapshot_date', '=', 'mx.max_date');
            })
            ->join('villages as v', 'v.id', '=', 'vds.village_id')
            ->leftJoin('alliances as a', 'a.id', '=', 'v.alliance_id')
            ->where('v.server_id', $serverId)
            ->whereIn('v.player_id', $playerIds)
            ->orderByDesc('vds.population')
            ->get(['v.player_id', 'a.tag', 'v.alliance_id']);

        $out = collect();
        foreach ($rows as $row) {
            $pid = (int) $row->player_id;
            if (! $out->has($pid)) {
                $tag = $row->tag;
                $tagStr = $tag !== null && trim((string) $tag) !== '' ? (string) $tag : null;
                $aid = $row->alliance_id !== null ? (int) $row->alliance_id : null;
                $out->put($pid, [
                    'tag' => $tagStr,
                    'alliance_id' => $aid,
                ]);
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $playerIds
     * @param  list<string>  $dateColumns
     * @return Collection<int, Collection<string, PlayerDailyStat>>
     */
    private function populationChangesForPlayers(array $playerIds, array $dateColumns): Collection
    {
        if ($playerIds === [] || $dateColumns === []) {
            return collect();
        }

        $stats = PlayerDailyStat::query()
            ->whereIn('player_id', $playerIds)
            ->whereIn('snapshot_date', $dateColumns)
            ->get(['player_id', 'snapshot_date', 'population_change']);

        return $stats->groupBy('player_id')->map(function (Collection $group) {
            return $group->keyBy(fn (PlayerDailyStat $s) => $s->snapshot_date->toDateString());
        });
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
     * @return array{date_columns: list<string>, rows: array{}, meta: array<string, mixed>}
     */
    private function emptyPayload(int $page, bool $hasCoordinates, int $effectiveTotal = 0, int $lastPage = 1): array
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
