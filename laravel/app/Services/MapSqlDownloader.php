<?php

namespace App\Services;

use App\Models\Server;
use Illuminate\Support\Facades\Http;

class MapSqlDownloader
{
    public function download(Server $server): ?string
    {
        if (! $server->is_active) {
            return null;
        }

        $url = $server->mapSqlUrl();
        if ($url === null) {
            return null;
        }

        $response = Http::timeout(120)
            ->accept('application/sql, text/plain, */*')
            ->get($url);

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();

        return $body !== '' ? $body : null;
    }
}
