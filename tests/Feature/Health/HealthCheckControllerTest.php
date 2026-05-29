<?php

namespace Tests\Feature\Health;

use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

class HealthCheckControllerTest extends TestCase
{
    public function test_health_returns_ok_when_database_is_available(): void
    {
        $response = $this->getJson(route('health.check'));

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'database',
                'timestamp',
            ])
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('database', 'ok');

        $this->assertNotEmpty($response->json('timestamp'));
    }

    public function test_health_is_accessible_without_authentication(): void
    {
        $response = $this->getJson(route('health.check'));

        $response->assertOk();
    }

    public function test_health_returns_503_when_database_is_unavailable(): void
    {
        $connection = \Mockery::mock();
        $connection->shouldReceive('getPdo')
            ->once()
            ->andThrow(new PDOException('Connection refused'));

        DB::shouldReceive('connection')
            ->once()
            ->andReturn($connection);

        $response = $this->getJson(route('health.check'));

        $response->assertStatus(503)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('database', 'error');
    }
}
