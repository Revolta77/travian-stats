<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class TribeController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = [];
        foreach (config('travian.tribes', []) as $id => $label) {
            $rows[] = [
                'id' => (int) $id,
                'label' => (string) $label,
            ];
        }
        usort($rows, fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return response()->json(['data' => $rows]);
    }
}
