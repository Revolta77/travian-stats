<?php

namespace Tests\Feature;

use Tests\TestCase;

class TribeControllerTest extends TestCase
{
    public function test_tribes_returns_sorted_list_from_config(): void
    {
        $response = $this->getJson('/api/tribes');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'label']]]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $ids = array_column($data, 'id');
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids);
    }
}
