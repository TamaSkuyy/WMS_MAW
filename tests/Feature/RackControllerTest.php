<?php

namespace Tests\Feature;

use App\Models\Rack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RackControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_index_displays_racks(): void
    {
        $racks = Rack::factory(3)->create();
        $response = $this->actingAs($this->user)->get(route('racks.index'));
        $response->assertStatus(200);
        foreach ($racks as $rack) {
            $response->assertSee($rack->code);
        }
    }

    public function test_create_shows_form(): void
    {
        $response = $this->actingAs($this->user)->get(route('racks.create'));
        $response->assertStatus(200);
    }

    public function test_store_creates_rack(): void
    {
        $data = ['code' => 'Z-99', 'zone' => 'Zona Z'];
        $response = $this->actingAs($this->user)->post(route('racks.store'), $data);
        $this->assertDatabaseHas('racks', ['code' => 'Z-99']);
        $response->assertRedirect();
    }

    public function test_store_rejects_duplicate_code(): void
    {
        Rack::factory()->create(['code' => 'A-01']);
        $response = $this->actingAs($this->user)->post(route('racks.store'), ['code' => 'A-01', 'zone' => 'Zona A']);
        $response->assertSessionHasErrors('code');
    }

    public function test_update_modifies_rack(): void
    {
        $rack = Rack::factory()->create(['code' => 'OLD-01']);
        $response = $this->actingAs($this->user)->put(route('racks.update', $rack), ['code' => 'NEW-01', 'zone' => 'Updated Zone']);
        $this->assertDatabaseHas('racks', ['id' => $rack->id, 'code' => 'NEW-01']);
        $response->assertRedirect();
    }

    public function test_destroy_deletes_rack(): void
    {
        $rack = Rack::factory()->create();
        $response = $this->actingAs($this->user)->delete(route('racks.destroy', $rack));
        $this->assertDatabaseMissing('racks', ['id' => $rack->id]);
        $response->assertRedirect();
    }
}
