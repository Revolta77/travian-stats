<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserStatsSearchRequest;
use App\Services\UserStatsService;
use Illuminate\Http\JsonResponse;

class UserStatsController extends Controller
{
    public function index(UserStatsSearchRequest $request, UserStatsService $service): JsonResponse
    {
        $v = $request->validated();

        $hasCoords = isset($v['x'], $v['y']);
        $x = $hasCoords ? (int) $v['x'] : null;
        $y = $hasCoords ? (int) $v['y'] : null;

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
            trim((string) ($v['alliance_filter'] ?? '')),
            $sortBy,
            $sortDir,
        );

        return response()->json($payload);
    }
}
