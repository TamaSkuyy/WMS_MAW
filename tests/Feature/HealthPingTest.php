<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthPingTest extends TestCase
{
    public function test_health_ping_endpoint_is_public_and_returns_pong(): void
    {
        $response = $this->get('/health/ping');

        $response->assertOk();
        $this->assertSame('pong', $response->getContent());
    }
}
