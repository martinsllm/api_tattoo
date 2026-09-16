<?php

namespace Tests\Feature\Health;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDOException;
use Tests\TestCase;

class HealthCheckControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.artist_images_disk'));
    }

    public function test_health_returns_ok_when_dependencies_are_available(): void
    {
        $response = $this->getJson(route('health.check'));

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'database',
                'queue',
                'storage',
                'timestamp',
            ])
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('database', 'ok')
            ->assertJsonPath('queue', 'ok')
            ->assertJsonPath('storage', 'ok');

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
            ->assertJsonPath('status', 'fail')
            ->assertJsonPath('database', 'fail');
    }

    public function test_health_returns_503_when_storage_is_unavailable(): void
    {
        $disk = \Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')
            ->once()
            ->with('.health-check', 'temp')
            ->andReturn(false);

        Storage::shouldReceive('disk')
            ->once()
            ->with(config('filesystems.artist_images_disk'))
            ->andReturn($disk);

        $response = $this->getJson(route('health.check'));

        $response->assertStatus(503)
            ->assertJsonPath('status', 'fail')
            ->assertJsonPath('database', 'ok')
            ->assertJsonPath('queue', 'ok')
            ->assertJsonPath('storage', 'fail');
    }
}
