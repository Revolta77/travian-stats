<?php

namespace Tests\Feature;

use App\Models\AllianceDailyStat;
use App\Models\PlayerDailyStat;
use App\Models\Server;
use App\Models\Village;
use App\Services\MapSqlImporter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MapSqlImporterTest extends TestCase
{
    use DatabaseTransactions;

    private function importSqlFromString(Server $server, string $sql): \App\Services\MapImportResult
    {
        $path = tempnam(sys_get_temp_dir(), 'map_x_world_');
        $this->assertNotFalse($path);
        try {
            file_put_contents($path, $sql);

            return app(MapSqlImporter::class)->import($server, $path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_imports_single_and_multi_tuple_insert(): void
    {
        $server = Server::query()->create([
            'name' => 'T',
            'slug' => 't',
            'base_url' => 'http://localhost',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $sql = <<<'SQL'
INSERT INTO `x_world` VALUES (256,55,200,2,46287,'Silent Hill',7908,'MysticOutlaw26',40,'TSK',196,NULL,TRUE,NULL,NULL,NULL),(200,60,200,2,999,'V2',7908,'MysticOutlaw26',0,'',50,NULL,FALSE,NULL,NULL,NULL);
SQL;

        $result = $this->importSqlFromString($server, $sql);

        $this->assertSame(2, $result->processed);
        $this->assertSame(0, $result->skipped);
        $this->assertSame(2, Village::query()->where('server_id', $server->id)->count());

        $playerStat = PlayerDailyStat::query()
            ->whereHas('player', fn ($q) => $q->where('server_id', $server->id)->where('external_id', 7908))
            ->first();
        $this->assertNotNull($playerStat);
        $this->assertSame(246, $playerStat->total_population);
        $this->assertSame(2, $playerStat->village_count);

        $allianceStat = AllianceDailyStat::query()
            ->whereHas('alliance', fn ($q) => $q->where('server_id', $server->id)->where('external_id', 40))
            ->first();
        $this->assertNotNull($allianceStat);
        $this->assertSame(196, $allianceStat->total_population);
        $this->assertSame(1, $allianceStat->village_count);
        $this->assertSame(1, $allianceStat->member_count);
    }

    public function test_parses_utf8_names_and_comma_inside_quotes(): void
    {
        $server = Server::query()->create([
            'name' => 'T2',
            'slug' => 't2',
            'base_url' => 'http://localhost',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $sql = <<<'SQL'
INSERT INTO `x_world` VALUES (11876,46,171,1,46372,'Γολεδιανα',7349,'العربي',40,'TSK',196,NULL,1,NULL,NULL,NULL),(1,1,1,1,999,'Name, With Comma',1,'P',0,'',10,NULL,0,NULL,NULL,NULL);
SQL;

        $result = $this->importSqlFromString($server, $sql);

        $this->assertSame(2, $result->processed);
        $this->assertSame(0, $result->skipped);

        $v1 = Village::query()->where('server_id', $server->id)->where('external_id', 46372)->first();
        $this->assertNotNull($v1);
        $this->assertSame('Γολεδιανα', $v1->name);

        $p = \App\Models\Player::query()->where('server_id', $server->id)->where('external_id', 7349)->first();
        $this->assertNotNull($p);
        $this->assertSame('العربي', $p->name);

        $v2 = Village::query()->where('server_id', $server->id)->where('external_id', 999)->first();
        $this->assertSame('Name, With Comma', $v2->name);
    }

    public function test_parses_backslash_escaped_quote_in_string(): void
    {
        $server = Server::query()->create([
            'name' => 'T3',
            'slug' => 't3',
            'base_url' => 'http://localhost',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $sql = <<<'SQL'
INSERT INTO `x_world` VALUES (1,1,1,1,888,'O\'Reilly',1,'P',0,'',10,NULL,0,NULL,NULL,NULL);
SQL;

        $result = $this->importSqlFromString($server, $sql);

        $this->assertSame(1, $result->processed);
        $v = Village::query()->where('external_id', 888)->first();
        $this->assertSame("O'Reilly", $v->name);
    }
}
