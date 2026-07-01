<?php

namespace Tests\Feature\Cors;

use Tests\TestCase;

class CorsConfigurationTest extends TestCase
{
    public function test_allowed_origin_receives_access_control_allow_origin_header(): void
    {
        config(['cors.allowed_origins' => ['https://allowed.test']]);

        $response = $this->getJson(route('health.check'), [
            'Origin' => 'https://allowed.test',
        ]);

        $response->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://allowed.test');
    }

    public function test_disallowed_origin_does_not_receive_its_origin_back(): void
    {
        config(['cors.allowed_origins' => ['https://allowed.test']]);

        $response = $this->getJson(route('health.check'), [
            'Origin' => 'https://evil.test',
        ]);

        $this->assertNotSame(
            'https://evil.test',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
    }

    public function test_preflight_request_from_allowed_origin_is_permitted(): void
    {
        config(['cors.allowed_origins' => ['https://allowed.test']]);

        $response = $this->call('OPTIONS', route('health.check'), [], [], [], [
            'HTTP_ORIGIN' => 'https://allowed.test',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $response->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://allowed.test');
    }
}
