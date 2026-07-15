<?php

namespace Tests\Feature;

use App\Models\DeliverySlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliverySlotControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_index_displays_all_six_slots(): void
    {
        $response = $this->actingAs($this->user)->get(route('delivery-slots.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Master/DeliverySlots/Index')
            ->has('slots', 6)
        );
    }

    public function test_edit_shows_form(): void
    {
        $slot = DeliverySlot::where('slot_number', 1)->first();

        $response = $this->actingAs($this->user)->get(route('delivery-slots.edit', $slot));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Master/DeliverySlots/Edit')
            ->where('slot.id', $slot->id)
        );
    }

    public function test_update_changes_time_window_and_label(): void
    {
        $slot = DeliverySlot::where('slot_number', 1)->first();

        $response = $this->actingAs($this->user)->put(route('delivery-slots.update', $slot), [
            'time_start' => '08:00',
            'time_end' => '10:00',
            'label' => 'Pagi Awal',
        ]);

        $response->assertRedirect(route('delivery-slots.index'));
        $this->assertDatabaseHas('delivery_slots', [
            'id' => $slot->id,
            'time_start' => '08:00:00',
            'time_end' => '10:00:00',
            'label' => 'Pagi Awal',
        ]);
    }

    public function test_update_rejects_end_time_before_start_time(): void
    {
        $slot = DeliverySlot::where('slot_number', 1)->first();

        $response = $this->actingAs($this->user)->put(route('delivery-slots.update', $slot), [
            'time_start' => '10:00',
            'time_end' => '09:00',
            'label' => 'Invalid',
        ]);

        $response->assertSessionHasErrors('time_end');
    }
}
