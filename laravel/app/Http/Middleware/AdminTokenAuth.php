<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AdminTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        if ($plain === null || $plain === '') {
            return response()->json(['message' => 'Neautorizovaný prístup.'], 401);
        }

        $hash = hash('sha256', $plain);
        $key = 'admin_token:'.$hash;
        if (! Cache::get($key)) {
            return response()->json(['message' => 'Neplatná alebo expirovaná relácia.'], 401);
        }

        return $next($request);
    }
}
