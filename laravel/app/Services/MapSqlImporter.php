<?php

namespace App\Services;

use App\Models\Alliance;
use App\Models\Player;
use App\Models\Server;
use App\Models\Village;
use App\Models\VillageDailyStat;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class MapSqlImporter
{
    private const X_WORLD_TABLE = 'x_world';

    private const MYSQL_LOCK_NAME = 'travian_x_world_import';

    private const MYSQL_LOCK_TIMEOUT = 30;

    private const ERROR_SAMPLE_LIMIT = 5;

    private const ROW_CHUNK = 500;

    /**
     * Celý obsah uloženého map.sql jedným volaním (vyžaduje PDO::MYSQL_ATTR_MULTI_STATEMENTS pri MySQL).
     * $mapSqlAbsolutePath = absolútna cesta k súboru (napr. storage/app/map-sql/…).
     *
     * @param  callable(array<string, mixed>): void|null  $progress
     * @param  string|null  $snapshotDate  Dátum snímky (Y-m-d) pre village_daily_stats; null = dnes v report_timezone
     */
    public function import(
        Server $server,
        string $mapSqlAbsolutePath,
        ?OutputInterface $output = null,
        ?callable $progress = null,
        ?string $snapshotDate = null
    ): MapImportResult {
        if (! app()->runningUnitTests()) {
            @set_time_limit(0);
            @ini_set('max_execution_time', '0');
        }

        if (! Schema::hasTable(self::X_WORLD_TABLE)) {
            throw new RuntimeException('Tabuľka x_world neexistuje — spustite php artisan migrate.');
        }

        if (! is_readable($mapSqlAbsolutePath)) {
            throw new RuntimeException('Súbor map.sql nie je čitateľný: '.$mapSqlAbsolutePath);
        }

        $tz = (string) config('travian.report_timezone', 'Europe/Bratislava');
        if ($snapshotDate !== null && $snapshotDate !== '') {
            $snapshotDate = Carbon::parse($snapshotDate, $tz)->toDateString();
        } else {
            $snapshotDate = Carbon::now($tz)->toDateString();
        }
        $yesterday = Carbon::parse($snapshotDate, $tz)->subDay()->toDateString();

        $processed = 0;
        $skipped = 0;
        $skippedBadColumns = 0;
        $skippedExceptions = 0;
        $sampleErrors = [];

        $driver = DB::getDriverName();
        $lockHeld = false;
        if ($driver === 'mysql') {
            $row = DB::selectOne('SELECT GET_LOCK(?, ?) AS l', [self::MYSQL_LOCK_NAME, self::MYSQL_LOCK_TIMEOUT]);
            if (! $row || (int) $row->l !== 1) {
                throw new RuntimeException(
                    'Import mapy už momentálne beží. Počkajte na dokončenie alebo skúste znova o chvíľu.'
                );
            }
            $lockHeld = true;
        }

        Log::info('map_sql: import začal', [
            'server_id' => $server->id,
            'driver' => $driver,
            'path' => $mapSqlAbsolutePath,
            'snapshot_date' => $snapshotDate,
        ]);

        $progressBar = null;
        $totalRows = 0;

        try {
            DB::table(self::X_WORLD_TABLE)->delete();

            if ($progress !== null) {
                $this->emitXWorldLoad($progress, 'executing_sql', [], 0, 0);
            }

            if ($driver === 'mysql') {
                DB::statement('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
            }

            $blob = file_get_contents($mapSqlAbsolutePath);
            if ($blob === false) {
                throw new RuntimeException('Nepodarilo sa načítať súbor: '.$mapSqlAbsolutePath);
            }

            try {
                MapSqlUnpreparedBatcher::execute($blob, function (string $chunk): void {
                    DB::unprepared($chunk);
                });
            } catch (Throwable $e) {
                throw new RuntimeException(
                    'Chyba pri vykonaní SQL súboru: '.$e->getMessage(),
                    0,
                    $e
                );
            }

            Log::info('map_sql: súbor vykonaný', [
                'path' => $mapSqlAbsolutePath,
                'bytes' => strlen($blob),
            ]);

            $totalRows = (int) DB::table(self::X_WORLD_TABLE)->count();

            if ($progress !== null) {
                $this->emitXWorldLoad(
                    $progress,
                    'x_world_loaded',
                    ['rows' => $totalRows],
                    1,
                    $totalRows
                );
            }

            if ($output !== null) {
                if ($totalRows === 0) {
                    $output->writeln('  <comment>Žiadne riadky x_world na import.</comment>');
                } else {
                    $output->writeln("  V x_world <info>{$totalRows}</info> riadkov, prebieha zápis do aplikácie…");
                    $progressBar = new ProgressBar($output, $totalRows);
                    ProgressBar::setFormatDefinition(
                        'travian_x_world',
                        ' %current%/%max% [%bar%] %percent:3s%%  |  uložené: %message%'
                    );
                    $progressBar->setFormat('travian_x_world');
                    $progressBar->setMessage('0');
                    $progressBar->setRedrawFrequency(max(1, (int) ($totalRows / 150)));
                    $progressBar->start();
                }
            }

            if ($progress !== null) {
                $progress([
                    'event' => 'start',
                    'total' => $totalRows,
                ]);
            }

            $rowNum = 0;

            DB::transaction(function () use (
                $server,
                $snapshotDate,
                $yesterday,
                &$processed,
                &$skipped,
                &$skippedBadColumns,
                &$skippedExceptions,
                &$sampleErrors,
                $progressBar,
                $progress,
                $totalRows,
                &$rowNum
            ) {
                $q = DB::table(self::X_WORLD_TABLE);
                if (Schema::hasColumn(self::X_WORLD_TABLE, 'id')) {
                    $q->orderBy('id');
                } else {
                    $q->orderBy('village_external_id')
                        ->orderBy('x')
                        ->orderBy('y')
                        ->orderBy('field_id');
                }
                $q->chunk(self::ROW_CHUNK, function ($rows) use (
                    $server,
                    $snapshotDate,
                    $yesterday,
                    &$processed,
                    &$skipped,
                    &$skippedBadColumns,
                    &$skippedExceptions,
                    &$sampleErrors,
                    $progressBar,
                    $progress,
                    $totalRows,
                    &$rowNum
                ) {
                    foreach ($rows as $row) {
                        try {
                            $values = $this->xWorldRowToValues($row);
                            if (count($values) !== 16) {
                                $skipped++;
                                $skippedBadColumns++;
                                if (count($sampleErrors) < self::ERROR_SAMPLE_LIMIT) {
                                    $sampleErrors[] = 'Neočakávaný počet stĺpcov z x_world: '.count($values);
                                }
                            } else {
                                $this->importRow($server, $values, $snapshotDate, $yesterday);
                                $processed++;
                            }
                        } catch (Throwable $e) {
                            $skipped++;
                            $skippedExceptions++;
                            $msg = $e->getMessage();
                            if (count($sampleErrors) < self::ERROR_SAMPLE_LIMIT) {
                                $sampleErrors[] = mb_substr($msg, 0, 280);
                            }
                            Log::debug('x_world: riadok preskočený', ['error' => $msg, 'x_world_id' => $row->id ?? null]);
                        }

                        $progressBar?->setMessage((string) $processed);
                        $progressBar?->advance();
                        $rowNum++;
                        $this->emitProgress($progress, $rowNum, $totalRows, $processed, $skipped, $skippedBadColumns, $skippedExceptions);
                    }
                });

                if ($progress !== null) {
                    $progress([
                        'event' => 'phase',
                        'phase' => 'aggregate',
                        'message_key' => 'aggregating_stats',
                        'message_params' => new \stdClass,
                    ]);
                }

                app(PlayerAllianceDailyStatAggregator::class)->syncForServerSnapshot($server, $snapshotDate, $yesterday);
            });
        } finally {
            if ($lockHeld) {
                DB::selectOne('SELECT RELEASE_LOCK(?) AS l', [self::MYSQL_LOCK_NAME]);
            }
        }

        if ($progressBar !== null) {
            $progressBar->finish();
            $output->writeln('');
        }

        if ($skipped > 0) {
            Log::info('x_world: import dokončený so preskočeniami', [
                'processed' => $processed,
                'skipped' => $skipped,
                'skipped_bad_columns' => $skippedBadColumns,
                'skipped_exceptions' => $skippedExceptions,
                'samples' => $sampleErrors,
            ]);
        }

        return new MapImportResult($processed, $skipped, $skippedBadColumns, $skippedExceptions, $sampleErrors);
    }

    /**
     * @param  callable(array<string, mixed>): void  $progress
     */
    /**
     * @param  array<string, int|string>  $params
     */
    private function emitXWorldLoad(callable $progress, string $messageKey, array $params, int $statements, int $rows): void
    {
        $progress([
            'event' => 'x_world_load',
            'phase' => 'x_world_sql',
            'message_key' => $messageKey,
            'message_params' => (object) $params,
            'statements' => $statements,
            'rows' => $rows,
        ]);
    }

    /**
     * @param  callable(array<string, mixed>): void|null  $progress
     */
    private function emitProgress(
        ?callable $progress,
        int $rowNum,
        int $totalRows,
        int $processed,
        int $skipped,
        int $skippedBadColumns,
        int $skippedExceptions
    ): void {
        if ($progress === null) {
            return;
        }

        $progress([
            'event' => 'progress',
            'current' => $rowNum,
            'total' => $totalRows,
            'saved' => $processed,
            'skipped' => $skipped,
            'skipped_bad_columns' => $skippedBadColumns,
            'skipped_exceptions' => $skippedExceptions,
        ]);
    }

    /**
     * @param  object{id?: int, field_id: int, x: int, y: int, tribe: int, village_external_id: int, village_name: string, player_external_id: int, player_name: string, alliance_external_id: int, alliance_tag: ?string, population: ?int, region: ?string, is_capital: mixed, is_city: mixed, has_harbor: mixed, victory_points: ?int}  $row
     * @return list<mixed>
     */
    private function xWorldRowToValues(object $row): array
    {
        return [
            (int) $row->field_id,
            (int) $row->x,
            (int) $row->y,
            (int) $row->tribe,
            (int) $row->village_external_id,
            (string) $row->village_name,
            (int) $row->player_external_id,
            (string) $row->player_name,
            (int) $row->alliance_external_id,
            $row->alliance_tag === null || $row->alliance_tag === '' ? '' : (string) $row->alliance_tag,
            $row->population === null ? null : (int) $row->population,
            $row->region === null || $row->region === '' ? null : (string) $row->region,
            (bool) $row->is_capital,
            $this->nullableBool($row->is_city),
            $this->nullableBool($row->has_harbor),
            $row->victory_points === null ? null : (int) $row->victory_points,
        ];
    }

    private function nullableBool(mixed $v): ?bool
    {
        if ($v === null) {
            return null;
        }

        return (bool) $v;
    }

    /**
     * @param  list<mixed>  $v
     */
    private function importRow(Server $server, array $v, string $snapshotDate, string $yesterday): void
    {
        $fieldId = (int) $v[0];
        $x = (int) $v[1];
        $y = (int) $v[2];
        $tribe = (int) $v[3];
        $villageExternalId = (int) $v[4];
        $villageName = (string) $v[5];
        $playerExternalId = (int) $v[6];
        $playerName = (string) $v[7];
        $allianceExternalId = (int) $v[8];
        $allianceTag = $v[9] === null ? '' : (string) $v[9];
        $population = $v[10] === null ? 0 : (int) $v[10];
        $region = $v[11] === null || $v[11] === '' ? null : (string) $v[11];
        $isCapital = (bool) ($v[12] ?? false);
        $isCity = $v[13] === null ? null : (bool) $v[13];
        $hasHarbor = $v[14] === null ? null : (bool) $v[14];
        $victoryPoints = $v[15] === null ? null : (int) $v[15];

        $player = Player::query()->updateOrCreate(
            [
                'server_id' => $server->id,
                'external_id' => $playerExternalId,
            ],
            ['name' => $playerName]
        );

        $allianceId = null;
        if ($allianceExternalId > 0) {
            $alliance = Alliance::query()->updateOrCreate(
                [
                    'server_id' => $server->id,
                    'external_id' => $allianceExternalId,
                ],
                ['tag' => $allianceTag]
            );
            $allianceId = $alliance->id;
        }

        $existing = Village::query()
            ->where('server_id', $server->id)
            ->where('external_id', $villageExternalId)
            ->first();

        $yesterdayStat = null;
        if ($existing !== null) {
            $yesterdayStat = VillageDailyStat::query()
                ->where('village_id', $existing->id)
                ->where('snapshot_date', $yesterday)
                ->first();
        }

        $prevPop = $yesterdayStat?->population !== null ? (int) $yesterdayStat->population : null;
        $prevStreak = $existing?->days_without_change;

        $metrics = DailyStatCalculator::compute($prevPop, $population, $prevStreak);

        $village = Village::query()->updateOrCreate(
            [
                'server_id' => $server->id,
                'external_id' => $villageExternalId,
            ],
            [
                'player_id' => $player->id,
                'alliance_id' => $allianceId,
                'field_id' => $fieldId,
                'x' => $x,
                'y' => $y,
                'tribe' => $tribe,
                'name' => $villageName,
                'region' => $region,
                'is_capital' => $isCapital,
                'is_city' => $isCity,
                'has_harbor' => $hasHarbor,
                'victory_points' => $victoryPoints,
                'days_without_change' => $metrics['days_without_change'],
                'last_seen_at' => now(),
            ]
        );

        VillageDailyStat::query()->updateOrCreate(
            [
                'village_id' => $village->id,
                'snapshot_date' => $snapshotDate,
            ],
            [
                'population' => $population,
                'population_change' => $metrics['population_change'],
            ]
        );
    }
}
