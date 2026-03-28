<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InactiveFinderSearchRequest;
use App\Services\InactiveFinderService;
use Illuminate\Http\JsonResponse;

class InactiveFinderController extends Controller
{
    public function index(InactiveFinderSearchRequest $request, InactiveFinderService $service): JsonResponse
    {
        $v = $request->validated();

        $payload = $service->search(
            (int) $v['server_id'],
            (int) $v['x'],
            (int) $v['y'],
            (int) ($v['page'] ?? 1),
        );

        return response()->json($payload);
    }
}
