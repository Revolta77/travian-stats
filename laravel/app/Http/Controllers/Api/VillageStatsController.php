<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VillageStatsSearchRequest;
use App\Services\VillageStatsService;
use Illuminate\Http\JsonResponse;

class VillageStatsController extends Controller
{
    public function index(VillageStatsSearchRequest $request, VillageStatsService $service): JsonResponse
    {
        $v = $request->validated();

        $tribe = $v['tribe'] ?? null;
        $tribeId = is_int($tribe) ? $tribe : null;

        $hasCoords = isset($v['x'], $v['y']);
        $x = $hasCoords ? (int) $v['x'] : null;
        $y = $hasCoords ? (int) $v['y'] : null;
        $pid = $v['player_id'] ?? null;
        $playerId = is_int($pid) ? $pid : null;
        $aid = $v['alliance_id'] ?? null;
        $allianceId = is_int($aid) ? $aid : null;

        $sortBy = (string) ($v['sort_by'] ?? ($hasCoords ? 'distance' : 'population'));
        $sortDir = (string) ($v['sort_dir'] ?? (($hasCoords && $sortBy === 'distance') ? 'asc' : 'desc'));

        if (! $hasCoords && $sortBy === 'distance') {
            $sortBy = 'population';
            $sortDir = 'desc';
        }

        $payload = $service->search(
            (int) $v['server_id'],
            $x,
            $y,
            (int) ($v['page'] ?? 1),
            trim((string) ($v['account_filter'] ?? '')),
            trim((string) ($v['village_filter'] ?? '')),
            (bool) ($v['exclude_my_account'] ?? false),
            trim((string) ($v['my_account_name'] ?? '')),
            trim((string) ($v['alliance_filter'] ?? '')),
            $tribeId,
            $playerId,
            $allianceId,
            $sortBy,
            $sortDir,
        );

        return response()->json($payload);
    }
}
