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

        $payload = $service->search(
            (int) $v['server_id'],
            (int) $v['x'],
            (int) $v['y'],
            (int) ($v['page'] ?? 1),
            trim((string) ($v['account_filter'] ?? '')),
            trim((string) ($v['village_filter'] ?? '')),
            (bool) ($v['exclude_my_account'] ?? false),
            trim((string) ($v['my_account_name'] ?? '')),
            trim((string) ($v['alliance_filter'] ?? '')),
            $tribeId,
        );

        return response()->json($payload);
    }
}
