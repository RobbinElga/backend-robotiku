<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_cors_preflight_allows_subdomain_origin(): void
    {
        $response = $this->withHeaders([
            'Origin'                         => 'https://app.robotiku.id',
            'Access-Control-Request-Method'  => 'GET',
            'Access-Control-Request-Headers' => 'Authorization,Content-Type',
        ])->options('/api/v1/media/payments/test.jpg');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://app.robotiku.id');
        $this->assertStringContainsStringIgnoringCase('authorization', (string) $response->headers->get('Access-Control-Allow-Headers'));
    }

    public function test_cors_preflight_allows_root_domain_origin(): void
    {
        $response = $this->withHeaders([
            'Origin'                         => 'https://robotiku.id',
            'Access-Control-Request-Method'  => 'POST',
            'Access-Control-Request-Headers' => 'Authorization',
        ])->options('/api/v1/auth/login');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://robotiku.id');
        $this->assertStringContainsStringIgnoringCase('authorization', (string) $response->headers->get('Access-Control-Allow-Headers'));
    }

    public function test_cors_preflight_allows_localhost_origin(): void
    {
        $response = $this->withHeaders([
            'Origin'                         => 'http://localhost:3000',
            'Access-Control-Request-Method'  => 'GET',
            'Access-Control-Request-Headers' => 'Authorization',
        ])->options('/api/v1/media/payments/test.jpg');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }
}
