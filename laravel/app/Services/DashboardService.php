<?php

namespace App\Services;

use App\Models\Server;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Súhrny pre aktívne servery: počty a aktívni hráči (aspoň jedna dedina so zmenou populácie &gt; 0
     * v niektorom z posledných 3 kalendárnych dní od najnovšieho importu dedín).
     *
     * @return list<array{
     *     server_id: int,
     *     name: string,
     *     slug: string,
     *     accounts_count: int,
     *     alliances_count: int,
     *     villages_count: int,
     *     active_players_count: int,
     *     activity_window_end: string|null,
     * }>
     */
    public function serverSummaries(): array
    {
        $servers = Server::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        if ($servers->isEmpty()) {
            return [];
        }

        $ids = $servers->pluck('id')->all();

        $accountsByServer = DB::table('villages')
            ->whereIn('server_id', $ids)
            ->groupBy('server_id')
            ->selectRaw('server_id, COUNT(DISTINCT player_id) as c')
            ->pluck('c', 'server_id');

        $villagesByServer = DB::table('villages')
            ->whereIn('server_id', $ids)
            ->groupBy('server_id')
            ->selectRaw('server_id, COUNT(*) as c')
            ->pluck('c', 'server_id');

        $alliancesByServer = DB::table('alliances')
            ->whereIn('server_id', $ids)
            ->groupBy('server_id')
            ->selectRaw('server_id, COUNT(*) as c')
            ->pluck('c', 'server_id');

        $latestRows = DB::table('village_daily_stats as vds')
            ->join('villages as v', 'v.id', '=', 'vds.village_id')
            ->whereIn('v.server_id', $ids)
            ->groupBy('v.server_id')
            ->selectRaw('v.server_id as server_id, MAX(vds.snapshot_date) as latest')
            ->get()
            ->keyBy('server_id');

        $activeByServer = [];
        foreach ($ids as $sid) {
            $activeByServer[$sid] = 0;
        }

        foreach ($latestRows as $serverId => $row) {
            $latest = Carbon::parse($row->latest)->startOfDay();
            $start = $latest->copy()->subDays(2)->toDateString();
            $end = $latest->toDateString();

            $cnt = (int) DB::table('villages as v')
                ->join('village_daily_stats as vds', 'vds.village_id', '=', 'v.id')
                ->where('v.server_id', (int) $serverId)
                ->whereBetween('vds.snapshot_date', [$start, $end])
                ->where('vds.population_change', '>', 0)
                ->selectRaw('COUNT(DISTINCT v.player_id) as c')
                ->value('c');

            $activeByServer[(int) $serverId] = $cnt;
        }

        return $servers->map(function (Server $s) use (
            $accountsByServer,
            $villagesByServer,
            $alliancesByServer,
            $latestRows,
            $activeByServer,
        ) {
            $sid = (int) $s->id;
            $latestRow = $latestRows->get($sid);
            $activityEnd = $latestRow !== null ? (string) $latestRow->latest : null;

            return [
                'server_id' => $sid,
                'name' => (string) $s->name,
                'slug' => (string) $s->slug,
                'accounts_count' => (int) ($accountsByServer[$sid] ?? 0),
                'alliances_count' => (int) ($alliancesByServer[$sid] ?? 0),
                'villages_count' => (int) ($villagesByServer[$sid] ?? 0),
                'active_players_count' => (int) ($activeByServer[$sid] ?? 0),
                'activity_window_end' => $activityEnd,
            ];
        })->values()->all();
    }
}
