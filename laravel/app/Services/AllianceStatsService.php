<?php

namespace App\Services;

use App\Models\AllianceDailyStat;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AllianceStatsService
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
     *     },
     * }
     */
    public function search(int $serverId, int $page = 1, string $tagFilter = ''): array
    {
        $page = max(1, min($page, self::MAX_PAGES));

        $latestDate = $this->latestSnapshotDateForServer($serverId);
        if ($latestDate === null) {
            return $this->emptyPayload($page);
        }

        $base = $this->allianceBaseQuery($serverId, $latestDate, $tagFilter);
        $matchCount = (int) (clone $base)->count();

        if ($matchCount === 0) {
            return $this->emptyPayload($page, 0, 1);
        }

        $effectiveTotal = min(self::MAX_RESULTS, $matchCount);
        $lastPage = $effectiveTotal > 0 ? (int) min(self::MAX_PAGES, (int) ceil($effectiveTotal / self::PER_PAGE)) : 1;

        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $offset = ($page - 1) * self::PER_PAGE;

        $ranked = (clone $base)
            ->orderByDesc('ads.total_population')
            ->orderBy('a.id')
            ->limit(self::MAX_RESULTS);

        $rows = DB::query()
            ->fromSub($ranked, 't')
            ->offset($offset)
            ->limit(self::PER_PAGE)
            ->get();

        if ($rows->isEmpty()) {
            return $this->emptyPayload($page, $effectiveTotal, $lastPage);
        }

        $allianceIds = $rows->pluck('alliance_id')->all();
        $dateColumns = $this->dateColumns();
        $changes = $this->populationChangesForAlliances($allianceIds, $dateColumns);

        $mapped = $rows->map(function ($row) use ($changes, $dateColumns) {
            $aid = (int) $row->alliance_id;
            $daily = [];
            foreach ($dateColumns as $d) {
                $daily[$d] = $changes->get($aid)?->get($d)?->population_change;
            }

            return [
                'alliance_id' => $aid,
                'tag' => (string) $row->tag,
                'member_count' => (int) $row->member_count,
                'village_count' => (int) $row->village_count,
                'total_population' => (int) $row->total_population,
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
            ],
        ];
    }

    private function allianceBaseQuery(int $serverId, string $latestDate, string $tagFilter): Builder
    {
        $q = DB::table('alliances as a')
            ->join('alliance_daily_stats as ads', function ($join) use ($latestDate): void {
                $join->on('a.id', '=', 'ads.alliance_id')
                    ->where('ads.snapshot_date', '=', $latestDate);
            })
            ->where('a.server_id', $serverId)
            ->select([
                'a.id as alliance_id',
                'a.tag',
                'ads.member_count',
                'ads.village_count',
                'ads.total_population',
            ]);

        $t = trim($tagFilter);
        if ($t !== '') {
            $like = '%'.$this->escapeLike(Str::lower($t)).'%';
            $q->whereRaw('LOWER(a.tag) LIKE ?', [$like]);
        }

        return $q;
    }

    private function latestSnapshotDateForServer(int $serverId): ?string
    {
        $d = DB::table('alliance_daily_stats as ads')
            ->join('alliances as a', 'a.id', '=', 'ads.alliance_id')
            ->where('a.server_id', $serverId)
            ->max('ads.snapshot_date');

        return $d !== null ? (string) $d : null;
    }

    /**
     * @param  list<int>  $allianceIds
     * @param  list<string>  $dateColumns
     * @return Collection<int, Collection<string, AllianceDailyStat>>
     */
    private function populationChangesForAlliances(array $allianceIds, array $dateColumns): Collection
    {
        if ($allianceIds === [] || $dateColumns === []) {
            return collect();
        }

        $stats = AllianceDailyStat::query()
            ->whereIn('alliance_id', $allianceIds)
            ->whereIn('snapshot_date', $dateColumns)
            ->get(['alliance_id', 'snapshot_date', 'population_change']);

        return $stats->groupBy('alliance_id')->map(function (Collection $group) {
            return $group->keyBy(fn (AllianceDailyStat $s) => $s->snapshot_date->toDateString());
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

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @return array{date_columns: list<string>, rows: array{}, meta: array<string, mixed>}
     */
    private function emptyPayload(int $page, int $effectiveTotal = 0, int $lastPage = 1): array
    {
        return [
            'date_columns' => $this->dateColumns(),
            'rows' => [],
            'meta' => [
                'current_page' => $page,
                'per_page' => self::PER_PAGE,
                'total' => $effectiveTotal,
                'last_page' => max(1, $lastPage),
            ],
        ];
    }
}
