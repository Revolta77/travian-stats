<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $expectedEmail = config('admin.email');
        $expectedPassword = config('admin.password');

        if ($expectedEmail === null || $expectedEmail === '' || $expectedPassword === null || $expectedPassword === '') {
            throw ValidationException::withMessages([
                'email' => ['Admin prihlásenie nie je nakonfigurované (ADMIN_EMAIL / ADMIN_PASSWORD v .env).'],
            ]);
        }

        if (! hash_equals((string) $expectedEmail, $request->input('email'))
            || ! hash_equals((string) $expectedPassword, $request->input('password'))) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $plain = Str::random(64);
        $hash = hash('sha256', $plain);
        $ttlHours = max(1, (int) config('admin.token_ttl_hours', 12));

        Cache::put('admin_token:'.$hash, true, now()->addHours($ttlHours));

        return response()->json([
            'token' => $plain,
            'token_type' => 'Bearer',
            'expires_in' => $ttlHours * 3600,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $plain = $request->bearerToken();
        if ($plain !== null && $plain !== '') {
            Cache::forget('admin_token:'.hash('sha256', $plain));
        }

        return response()->json(['message' => 'Odhlásené.']);
    }

    public function me(): JsonResponse
    {
        return response()->json([
            'email' => config('admin.email'),
        ]);
    }
}
