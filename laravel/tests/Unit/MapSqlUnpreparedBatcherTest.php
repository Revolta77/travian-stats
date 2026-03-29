<?php

namespace Tests\Unit;

use App\Services\MapSqlUnpreparedBatcher;
use PHPUnit\Framework\TestCase;

class MapSqlUnpreparedBatcherTest extends TestCase
{
    public function test_split_statements_respects_semicolon_inside_single_quotes(): void
    {
        $sql = "SELECT ';' AS x;\nINSERT INTO t VALUES (1);";
        $parts = MapSqlUnpreparedBatcher::splitStatements($sql);
        $this->assertCount(2, $parts);
        $this->assertStringContainsString("';'", $parts[0]);
        $this->assertStringContainsString('INSERT', $parts[1]);
    }

    public function test_split_statements_respects_double_quotes(): void
    {
        $sql = 'SELECT ";"; SELECT 2;';
        $parts = MapSqlUnpreparedBatcher::splitStatements($sql);
        $this->assertCount(2, $parts);
    }

    public function test_split_value_tuples_greek_arabic_and_comma_inside_string(): void
    {
        $valuePart = "(11876,46,171,1,46372,'Γολεδιανα',7349,'العربي',40,'TSK',196,NULL,1,NULL,NULL,NULL),(1,1,1,1,999,'Name, With Comma',1,'P',0,'',10,NULL,0,NULL,NULL,NULL)";
        $rows = MapSqlUnpreparedBatcher::splitValueTuples($valuePart);
        $this->assertCount(2, $rows);
        $this->assertStringContainsString('Γολεδιανα', $rows[0]);
        $this->assertStringContainsString('Name, With Comma', $rows[1]);
    }

    public function test_execute_batches_multiple_chunks(): void
    {
        $received = [];
        MapSqlUnpreparedBatcher::execute('SELECT 1;SELECT 2;SELECT 3;', function (string $q) use (&$received) {
            $received[] = $q;
        }, 16);

        $this->assertNotEmpty($received);
        $joined = implode('|', $received);
        $this->assertStringContainsString('SELECT 1', $joined);
        $this->assertStringContainsString('SELECT 2', $joined);
        $this->assertStringContainsString('SELECT 3', $joined);
        foreach ($received as $chunk) {
            $this->assertLessThanOrEqual(16, strlen($chunk), $chunk);
        }
    }
}
