<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_returns_token_when_credentials_match_env_config(): void
    {
        Config::set('admin.email', 'admin@test.com');
        Config::set('admin.password', 'tajne-heslo');

        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'tajne-heslo',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'expires_in']);
    }

    public function test_login_fails_on_wrong_password(): void
    {
        Config::set('admin.email', 'admin@test.com');
        Config::set('admin.password', 'ok');

        $this->postJson('/api/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'zle',
        ])->assertUnprocessable();
    }

    public function test_admin_servers_requires_bearer_token(): void
    {
        $this->getJson('/api/admin/servers')->assertUnauthorized();
    }
}
