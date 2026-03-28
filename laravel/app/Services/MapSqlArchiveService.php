<?php

namespace App\Services;

use App\Models\Server;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class MapSqlArchiveService
{
    private const DISK = 'local';

    private const KEEP_DAYS = 7;

    public function saveAndPrune(Server $server, string $sqlBody): string
    {
        $date = Carbon::now((string) config('travian.report_timezone', 'Europe/Bratislava'))->toDateString();
        $relative = "map-sql/{$server->id}/{$date}.sql";

        Storage::disk(self::DISK)->put($relative, $sqlBody);

        $this->pruneOlderThan($server, self::KEEP_DAYS);

        return $relative;
    }

    /**
     * Uloží map.sql pre konkrétny dátum (Y-m-d), napr. historický import z adminu.
     */
    public function saveForDate(Server $server, string $dateYmd, string $sqlBody): string
    {
        $normalized = Carbon::parse($dateYmd)->toDateString();
        $relative = "map-sql/{$server->id}/{$normalized}.sql";

        Storage::disk(self::DISK)->put($relative, $sqlBody);

        $this->pruneOlderThan($server, self::KEEP_DAYS);

        return $relative;
    }

    public function pruneOlderThan(Server $server, int $keepDays): void
    {
        $dir = "map-sql/{$server->id}";
        if (! Storage::disk(self::DISK)->exists($dir)) {
            return;
        }

        $cutoff = Carbon::now((string) config('travian.report_timezone', 'Europe/Bratislava'))
            ->subDays($keepDays)
            ->startOfDay();

        foreach (Storage::disk(self::DISK)->files($dir) as $file) {
            $base = basename($file);
            if (preg_match('/^(\d{4}-\d{2}-\d{2})\.sql$/', $base, $m)) {
                if (Carbon::parse($m[1])->lt($cutoff)) {
                    Storage::disk(self::DISK)->delete($file);
                }
            }
        }
    }
}
