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

    /**
     * Healthcheck Docker harus tetap 200 saat maintenance mode — kalau tidak,
     * `deploy-production.sh --rebuild` selalu gagal menunggu "app healthy".
     */
    public function test_health_endpoints_stay_up_during_maintenance_mode(): void
    {
        $this->artisan('down')->run();

        try {
            $this->get('/health/ping')->assertOk();
            $this->get('/up')->assertOk();
        } finally {
            $this->artisan('up')->run();
        }
    }
}
