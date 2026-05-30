<?php

namespace Tests\Feature\Docs;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScrambleDocumentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'array']);

        $compiledPath = sys_get_temp_dir().'/laravel-view-cache-scramble-docs';

        if (! is_dir($compiledPath)) {
            mkdir($compiledPath, 0777, true);
        }

        config(['view.compiled' => $compiledPath]);
    }

    public function test_docs_ui_is_accessible(): void
    {
        $response = $this->get('/docs');

        $response->assertOk();
    }

    public function test_docs_json_describes_api_v1_with_security_and_pagination(): void
    {
        $response = $this->get('/docs.json');

        $response->assertOk();

        $spec = $response->json();

        $this->assertSame('API Tattoo', $spec['info']['title'] ?? null);
        $this->assertArrayHasKey('http', $spec['components']['securitySchemes'] ?? []);

        $artistsSchema = $spec['paths']['/artists']['get']['responses']['200']['content']['application/json']['schema'] ?? [];
        $this->assertArrayHasKey('data', $artistsSchema['properties'] ?? []);
        $this->assertArrayHasKey('links', $artistsSchema['properties'] ?? []);
        $this->assertArrayHasKey('meta', $artistsSchema['properties'] ?? []);
        $this->assertArrayHasKey('message', $artistsSchema['properties'] ?? []);
        $this->assertNotEmpty($artistsSchema['properties']['data']['items'] ?? null);

        $this->assertArrayHasKey('404', $spec['paths']['/artists/{id}']['get']['responses'] ?? []);
        $this->assertArrayHasKey('403', $spec['paths']['/admin/artists/{id}/deactivate']['patch']['responses'] ?? []);
        $this->assertArrayHasKey('422', $spec['paths']['/login']['post']['responses'] ?? []);
        $this->assertArrayHasKey('401', $spec['paths']['/me']['get']['responses'] ?? []);
    }
}
