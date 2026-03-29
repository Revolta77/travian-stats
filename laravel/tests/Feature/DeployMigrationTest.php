<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DeployMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_run_migrate_404_when_token_not_configured(): void
    {
        Config::set('travian.deployment.token_migration', '');

        $this->getJson('/api/run_migrate?token=anything')
            ->assertStatus(404);
    }

    public function test_run_migrate_403_on_bad_token(): void
    {
        Config::set('travian.deployment.token_migration', 'secret-token');

        $this->getJson('/api/run_migrate?token=wrong')
            ->assertStatus(403)
            ->assertJsonPath('ok', false);
    }

    public function test_run_migrate_succeeds_with_valid_token(): void
    {
        Config::set('travian.deployment.token_migration', 'good-token');

        $this->getJson('/api/run_migrate?token=good-token')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'exit_code', 'output']);
    }
}
