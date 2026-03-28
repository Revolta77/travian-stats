<?php

namespace App\Services;

use App\Models\AllianceDailyStat;
use App\Models\PlayerDailyStat;
use App\Models\Server;
use Illuminate\Support\Facades\DB;

class PlayerAllianceDailyStatAggregator
{
    /**
     * Po importe dedín pre daný deň: agreguje village_daily_stats na hráčov a aliance.
     */
    public function syncForServerSnapshot(Server $server, string $snapshotDate, string $yesterday): void
    {
        $this->deletePlayerStatsForServerDate($server, $snapshotDate);
        $this->deleteAllianceStatsForServerDate($server, $snapshotDate);

        $this->syncPlayers($server, $snapshotDate, $yesterday);
        $this->syncAlliances($server, $snapshotDate, $yesterday);
    }

    private function deletePlayerStatsForServerDate(Server $server, string $snapshotDate): void
    {
        PlayerDailyStat::query()
            ->where('snapshot_date', $snapshotDate)
            ->whereHas('player', function ($q) use ($server): void {
                $q->where('server_id', $server->id);
            })
            ->delete();
    }

    private function deleteAllianceStatsForServerDate(Server $server, string $snapshotDate): void
    {
        AllianceDailyStat::query()
            ->where('snapshot_date', $snapshotDate)
            ->whereHas('alliance', function ($q) use ($server): void {
                $q->where('server_id', $server->id);
            })
            ->delete();
    }

    private function syncPlayers(Server $server, string $snapshotDate, string $yesterday): void
    {
        $aggregates = DB::table('village_daily_stats as vds')
            ->join('villages as v', 'v.id', '=', 'vds.village_id')
            ->where('v.server_id', $server->id)
            ->where('vds.snapshot_date', $snapshotDate)
            ->groupBy('v.player_id')
            ->selectRaw('v.player_id as player_id, SUM(vds.population) as total_population, COUNT(*) as village_count')
            ->get();

        if ($aggregates->isEmpty()) {
            return;
        }

        $playerIds = $aggregates->pluck('player_id')->all();

        $prevByPlayer = PlayerDailyStat::query()
            ->where('snapshot_date', $yesterday)
            ->whereIn('player_id', $playerIds)
            ->get()
            ->keyBy('player_id');

        $now = now();
        $toInsert = [];

        foreach ($aggregates as $row) {
            $prev = $prevByPlayer->get($row->player_id);
            $totalPop = (int) $row->total_population;
            $villageCount = (int) $row->village_count;

            $metrics = DailyStatCalculator::compute(
                $prev !== null ? (int) $prev->total_population : null,
                $totalPop,
                $prev !== null ? (int) $prev->days_without_change : null
            );

            $villageCountChange = $prev === null
                ? null
                : $villageCount - (int) $prev->village_count;

            $toInsert[] = [
                'player_id' => (int) $row->player_id,
                'snapshot_date' => $snapshotDate,
                'total_population' => $totalPop,
                'village_count' => $villageCount,
                'population_change' => $metrics['population_change'],
                'village_count_change' => $villageCountChange,
                'days_without_change' => $metrics['days_without_change'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($toInsert, 500) as $chunk) {
            PlayerDailyStat::query()->insert($chunk);
        }
    }

    private function syncAlliances(Server $server, string $snapshotDate, string $yesterday): void
    {
        $aggregates = DB::table('village_daily_stats as vds')
            ->join('villages as v', 'v.id', '=', 'vds.village_id')
            ->where('v.server_id', $server->id)
            ->where('vds.snapshot_date', $snapshotDate)
            ->whereNotNull('v.alliance_id')
            ->groupBy('v.alliance_id')
            ->selectRaw('v.alliance_id as alliance_id, SUM(vds.population) as total_population, COUNT(*) as village_count, COUNT(DISTINCT v.player_id) as member_count')
            ->get();

        if ($aggregates->isEmpty()) {
            return;
        }

        $allianceIds = $aggregates->pluck('alliance_id')->all();

        $prevByAlliance = AllianceDailyStat::query()
            ->where('snapshot_date', $yesterday)
            ->whereIn('alliance_id', $allianceIds)
            ->get()
            ->keyBy('alliance_id');

        $now = now();
        $toInsert = [];

        foreach ($aggregates as $row) {
            $prev = $prevByAlliance->get($row->alliance_id);
            $totalPop = (int) $row->total_population;
            $villageCount = (int) $row->village_count;
            $memberCount = (int) $row->member_count;

            $metrics = DailyStatCalculator::compute(
                $prev !== null ? (int) $prev->total_population : null,
                $totalPop,
                $prev !== null ? (int) $prev->days_without_change : null
            );

            $villageCountChange = $prev === null
                ? null
                : $villageCount - (int) $prev->village_count;

            $memberCountChange = $prev === null
                ? null
                : $memberCount - (int) $prev->member_count;

            $toInsert[] = [
                'alliance_id' => (int) $row->alliance_id,
                'snapshot_date' => $snapshotDate,
                'total_population' => $totalPop,
                'village_count' => $villageCount,
                'member_count' => $memberCount,
                'population_change' => $metrics['population_change'],
                'village_count_change' => $villageCountChange,
                'member_count_change' => $memberCountChange,
                'days_without_change' => $metrics['days_without_change'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($toInsert, 500) as $chunk) {
            AllianceDailyStat::query()->insert($chunk);
        }
    }
}
