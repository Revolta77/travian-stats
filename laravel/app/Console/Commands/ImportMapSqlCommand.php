<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\MapSqlArchiveService;
use App\Services\MapSqlDownloader;
use App\Services\MapSqlImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportMapSqlCommand extends Command
{
    protected $signature = 'travian:import-map
                            {server? : Slug servera (ak nie je --all)}
                            {--all : Všetky aktívne servery s vyplneným base_url}';

    protected $description = 'Stiahne map.sql (base_url + /map.sql) a importuje x_world do databázy';

    public function handle(MapSqlDownloader $downloader, MapSqlImporter $importer, MapSqlArchiveService $archive): int
    {
        if ($this->option('all')) {
            return $this->runForServers(
                Server::query()
                    ->where('is_active', true)
                    ->whereNotNull('base_url')
                    ->where('base_url', '!=', '')
                    ->orderBy('name')
                    ->get(),
                $downloader,
                $importer,
                $archive
            );
        }

        $slug = $this->argument('server');

        if ($slug === null) {
            $servers = Server::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['slug', 'name', 'base_url', 'is_active']);

            if ($servers->isEmpty()) {
                $this->warn('Žiadny aktívny server. Najprv vlož záznam do tabuľky servers.');

                return self::SUCCESS;
            }

            $this->table(
                ['slug', 'name', 'base_url', 'map.sql'],
                $servers->map(fn (Server $s) => [
                    $s->slug,
                    $s->name,
                    $s->base_url ?? '—',
                    $s->mapSqlUrl() ?? '—',
                ])->all()
            );
            $this->line('Použitie: php artisan travian:import-map {slug}   alebo   php artisan travian:import-map --all');

            return self::SUCCESS;
        }

        $server = Server::query()->where('slug', $slug)->first();
        if ($server === null) {
            $this->error("Server so slugom \"{$slug}\" neexistuje.");

            return self::FAILURE;
        }

        return $this->runForServers(collect([$server]), $downloader, $importer, $archive);
    }

    /**
     * @param  Collection<int, Server>  $servers
     */
    private function runForServers(Collection $servers, MapSqlDownloader $downloader, MapSqlImporter $importer, MapSqlArchiveService $archive): int
    {
        $failed = false;

        foreach ($servers as $server) {
            $this->info("Server: {$server->name} ({$server->slug})");

            if (! $server->is_active) {
                $this->line('  Neaktívny server — map.sql sa nestahuje (null).');
                continue;
            }

            $sql = $downloader->download($server);

            if ($sql === null) {
                $this->warn('  map.sql sa nepodarilo stiahnuť (neaktívny server, chýba base_url alebo HTTP zlyhalo).');
                $failed = true;

                continue;
            }

            $this->info('  Stiahnuté: '.strlen($sql).' bajtov.');

            $storedPath = $archive->saveAndPrune($server, $sql);
            $this->line('  Archív: storage/app/'.$storedPath.' (max. 7 dní späť).');

            try {
                $absPath = Storage::disk('local')->path($storedPath);
                $result = $importer->import($server, $absPath, $this->output);
                $this->info("  Import: {$result->processed} riadkov, preskočených: {$result->skipped}.");
                if ($result->skipped > 0) {
                    $this->warn("    (zlých stĺpcov: {$result->skippedBadColumns}, výnimiek: {$result->skippedExceptions})");
                    foreach ($result->sampleErrors as $sample) {
                        $this->line('    · '.mb_substr($sample, 0, 200));
                    }
                    $this->line('    Podrobnosti pri LOG_LEVEL=debug; súhrn v logu ako info „x_world: import dokončený so preskočeniami“.');
                }
            } catch (Throwable $e) {
                $this->error('  Import zlyhal: '.$e->getMessage());
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
