<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServerImportUploadRequest;
use App\Models\AllianceDailyStat;
use App\Models\PlayerDailyStat;
use App\Models\Server;
use App\Models\VillageDailyStat;
use App\Services\MapSqlArchiveService;
use App\Services\MapSqlDownloader;
use App\Services\MapSqlImporter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminServerImportController extends Controller
{
    /**
     * @return list<array{key: string}>
     */
    private function snapshotImportWarnings(Server $server, string $snapshotDate): array
    {
        $tz = (string) config('travian.report_timezone', 'Europe/Bratislava');
        $today = Carbon::now($tz)->toDateString();
        $warnings = [];

        if ($snapshotDate < $today) {
            $warnings[] = ['key' => 'snapshot_past_date'];
        }

        $hasLaterVillages = VillageDailyStat::query()
            ->where('snapshot_date', '>', $snapshotDate)
            ->whereExists(function ($q) use ($server): void {
                $q->selectRaw('1')
                    ->from('villages')
                    ->whereColumn('villages.id', 'village_daily_stats.village_id')
                    ->where('villages.server_id', $server->id);
            })
            ->exists();

        $hasLaterPlayers = PlayerDailyStat::query()
            ->where('snapshot_date', '>', $snapshotDate)
            ->whereHas('player', function ($q) use ($server): void {
                $q->where('server_id', $server->id);
            })
            ->exists();

        $hasLaterAlliances = AllianceDailyStat::query()
            ->where('snapshot_date', '>', $snapshotDate)
            ->whereHas('alliance', function ($q) use ($server): void {
                $q->where('server_id', $server->id);
            })
            ->exists();

        if ($hasLaterVillages || $hasLaterPlayers || $hasLaterAlliances) {
            $warnings[] = ['key' => 'later_daily_records'];
        }

        return $warnings;
    }

    public function stream(
        Server $server,
        MapSqlDownloader $downloader,
        MapSqlArchiveService $archive,
        MapSqlImporter $importer
    ): StreamedResponse {
        return response()->stream(function () use ($server, $downloader, $archive, $importer) {
            if (! app()->runningUnitTests()) {
                @set_time_limit(0);
                @ini_set('max_execution_time', '0');
                ignore_user_abort(true);
            }

            $emit = static function (array $payload): void {
                echo json_encode($payload, JSON_UNESCAPED_UNICODE)."\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            try {
                if (! $server->is_active) {
                    $emit(['event' => 'error', 'message_key' => 'error_server_inactive']);

                    return;
                }

                $emit(['event' => 'phase', 'phase' => 'download', 'message_key' => 'phase_download']);

                $sql = $downloader->download($server);
                if ($sql === null) {
                    $emit(['event' => 'error', 'message_key' => 'error_download_failed']);

                    return;
                }

                $relativePath = $archive->saveAndPrune($server, $sql);
                $emit(['event' => 'phase', 'phase' => 'archive', 'message_key' => 'phase_archive', 'bytes' => strlen($sql)]);

                $savedSqlPath = Storage::disk('local')->path($relativePath);

                $result = $importer->import($server, $savedSqlPath, null, static function (array $p) use ($emit): void {
                    $emit($p);
                });

                $snapshotUsed = Carbon::now((string) config('travian.report_timezone', 'Europe/Bratislava'))->toDateString();

                $emit([
                    'event' => 'done',
                    'processed' => $result->processed,
                    'skipped' => $result->skipped,
                    'skipped_bad_columns' => $result->skippedBadColumns,
                    'skipped_exceptions' => $result->skippedExceptions,
                    'sample_errors' => $result->sampleErrors,
                    'snapshot_date' => $snapshotUsed,
                    'import_warnings' => $this->snapshotImportWarnings($server, $snapshotUsed),
                ]);
            } catch (Throwable $e) {
                Log::error('Admin map import zlyhal', [
                    'server_id' => $server->id,
                    'message' => $e->getMessage(),
                    'exception' => $e::class,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $emit([
                    'event' => 'error',
                    'message_key' => 'error_exception',
                    'detail' => $e->getMessage(),
                ]);
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=UTF-8',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function streamUpload(
        Server $server,
        ServerImportUploadRequest $request,
        MapSqlArchiveService $archive,
        MapSqlImporter $importer
    ): StreamedResponse {
        return response()->stream(function () use ($server, $request, $archive, $importer) {
            if (! app()->runningUnitTests()) {
                @set_time_limit(0);
                @ini_set('max_execution_time', '0');
                ignore_user_abort(true);
            }

            $emit = static function (array $payload): void {
                echo json_encode($payload, JSON_UNESCAPED_UNICODE)."\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            try {
                $snapshotDate = Carbon::parse($request->validated('snapshot_date'))
                    ->toDateString();

                $emit(['event' => 'phase', 'phase' => 'upload', 'message_key' => 'phase_upload']);

                $sqlBody = '';
                if ($request->hasFile('sql_file')) {
                    $uploaded = $request->file('sql_file');
                    $tmp = $uploaded->getRealPath();
                    if ($tmp !== false && is_readable($tmp)) {
                        $read = file_get_contents($tmp);
                        $sqlBody = is_string($read) ? $read : '';
                    }
                } else {
                    $sqlBody = (string) $request->input('sql', '');
                }
                $sqlBody = trim($sqlBody);
                if ($sqlBody === '') {
                    $emit(['event' => 'error', 'message_key' => 'error_empty_sql']);

                    return;
                }

                $relativePath = $archive->saveForDate($server, $snapshotDate, $sqlBody);
                $emit([
                    'event' => 'phase',
                    'phase' => 'archive',
                    'message_key' => 'phase_archive',
                    'bytes' => strlen($sqlBody),
                ]);

                $savedSqlPath = Storage::disk('local')->path($relativePath);

                $result = $importer->import(
                    $server,
                    $savedSqlPath,
                    null,
                    static function (array $p) use ($emit): void {
                        $emit($p);
                    },
                    $snapshotDate
                );

                $emit([
                    'event' => 'done',
                    'processed' => $result->processed,
                    'skipped' => $result->skipped,
                    'skipped_bad_columns' => $result->skippedBadColumns,
                    'skipped_exceptions' => $result->skippedExceptions,
                    'sample_errors' => $result->sampleErrors,
                    'snapshot_date' => $snapshotDate,
                    'import_warnings' => $this->snapshotImportWarnings($server, $snapshotDate),
                ]);
            } catch (Throwable $e) {
                Log::error('Admin map import (upload) zlyhal', [
                    'server_id' => $server->id,
                    'message' => $e->getMessage(),
                    'exception' => $e::class,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $emit([
                    'event' => 'error',
                    'message_key' => 'error_exception',
                    'detail' => $e->getMessage(),
                ]);
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=UTF-8',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
