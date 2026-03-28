<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AllianceStatsSearchRequest;
use App\Services\AllianceStatsService;
use Illuminate\Http\JsonResponse;

class AllianceStatsController extends Controller
{
    public function index(AllianceStatsSearchRequest $request, AllianceStatsService $service): JsonResponse
    {
        $v = $request->validated();

        $payload = $service->search(
            (int) $v['server_id'],
            (int) ($v['page'] ?? 1),
            trim((string) ($v['tag_filter'] ?? '')),
        );

        return response()->json($payload);
    }
}
