<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class DeployMigrationController extends Controller
{
    public function run(Request $request): JsonResponse
    {
        $expected = (string) config('travian.deployment.token_migration', '');
        if ($expected === '') {
            abort(404);
        }

        $token = (string) $request->query('token', '');
        if ($token === '' || ! hash_equals($expected, $token)) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            $exitCode = Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();

            return response()->json([
                'ok' => $exitCode === 0,
                'exit_code' => $exitCode,
                'output' => $output !== '' ? $output : '(no output)',
            ], $exitCode === 0 ? 200 : 500);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'exit_code' => 1,
                'output' => $e->getMessage(),
            ], 500);
        }
    }
}
