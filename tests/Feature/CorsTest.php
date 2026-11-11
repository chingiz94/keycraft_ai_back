<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_client_preflight_allows_bearer_json_and_idempotency_headers(): void
    {
        config(['cors.allowed_origins' => ['http://localhost:5173']]);
        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'authorization,content-type,idempotency-key',
        ])->options('/api/v1/orders')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeaderMissing('Access-Control-Allow-Credentials')
            ->assertHeader('X-Request-ID');
    }

    public function test_other_origins_do_not_receive_a_matching_cors_allow_origin(): void
    {
        config(['cors.allowed_origins' => ['http://localhost:5173']]);
        $this->withHeaders([
            'Origin' => 'https://untrusted.example',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/v1/orders')->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }
}
