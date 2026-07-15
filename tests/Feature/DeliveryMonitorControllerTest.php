<?php

namespace Tests\Feature;

use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryMonitorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_with_real_data(): void
    {
        Supplier::factory()->create();

        $response = $this->get(route('delivery-monitor'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('DeliveryMonitor/Index')
            ->has('suppliers', 1)
            ->has('slots', 6)
            ->has('selectedDate')
        );
    }

    public function test_index_accepts_a_date_query_param(): void
    {
        $response = $this->get(route('delivery-monitor', ['date' => '2026-01-01']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->where('selectedDate', '2026-01-01'));
    }
}
