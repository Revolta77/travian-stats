<?php

namespace Tests\Feature;

use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServersTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_servers_returns_json_array(): void
    {
        Server::query()->create([
            'name' => 'Test S1',
            'slug' => 'test-s1',
            'base_url' => null,
            'timezone' => 'Europe/Bratislava',
            'is_active' => true,
        ]);

        $res = $this->getJson('/api/dashboard/servers');

        $res->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'server_id',
                        'name',
                        'slug',
                        'accounts_count',
                        'alliances_count',
                        'villages_count',
                        'active_players_count',
                        'activity_window_end',
                    ],
                ],
            ]);
    }
}
