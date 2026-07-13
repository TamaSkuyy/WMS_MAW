<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_employee_can_be_assigned_a_shift(): void
    {
        $shift = Shift::factory()->create([
            'name' => 'Shift Pagi',
            'code' => 'P',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $employee = Employee::factory()->create(['shift_id' => $shift->id]);

        $this->assertTrue($employee->shift->is($shift));
        $this->assertTrue($shift->employees->contains($employee));
    }

    public function test_index_displays_shifts(): void
    {
        Shift::factory()->create(['name' => 'Shift Pagi', 'code' => 'P']);

        $response = $this->actingAs($this->user)->get(route('shifts.index'));

        $response->assertStatus(200);
    }

    public function test_create_shows_form(): void
    {
        $response = $this->actingAs($this->user)->get(route('shifts.create'));

        $response->assertStatus(200);
    }

    public function test_store_creates_shift(): void
    {
        $data = [
            'name' => 'Shift Pagi',
            'code' => 'P',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'Aktif',
        ];

        $response = $this->actingAs($this->user)->post(route('shifts.store'), $data);

        $this->assertDatabaseHas('shifts', ['name' => 'Shift Pagi', 'code' => 'P']);
        $response->assertRedirect(route('shifts.index'));
    }

    public function test_store_accepts_overnight_shift(): void
    {
        $data = [
            'name' => 'Shift Malam',
            'code' => 'M',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'status' => 'Aktif',
        ];

        $response = $this->actingAs($this->user)->post(route('shifts.store'), $data);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('shifts', ['name' => 'Shift Malam', 'code' => 'M']);
    }

    public function test_store_rejects_duplicate_name(): void
    {
        Shift::factory()->create(['name' => 'Shift Pagi', 'code' => 'P']);

        $data = [
            'name' => 'Shift Pagi',
            'code' => 'X',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'Aktif',
        ];

        $response = $this->actingAs($this->user)->post(route('shifts.store'), $data);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('shifts', 1);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        Shift::factory()->create(['name' => 'Shift Pagi', 'code' => 'P']);

        $data = [
            'name' => 'Shift Lain',
            'code' => 'P',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'Aktif',
        ];

        $response = $this->actingAs($this->user)->post(route('shifts.store'), $data);

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseCount('shifts', 1);
    }

    public function test_store_rejects_invalid_time_format(): void
    {
        $data = [
            'name' => 'Shift Pagi',
            'code' => 'P',
            'start_time' => 'not-a-time',
            'end_time' => '16:00',
            'status' => 'Aktif',
        ];

        $response = $this->actingAs($this->user)->post(route('shifts.store'), $data);

        $response->assertSessionHasErrors('start_time');
        $this->assertDatabaseCount('shifts', 0);
    }

    public function test_edit_shows_form(): void
    {
        $shift = Shift::factory()->create();

        $response = $this->actingAs($this->user)->get(route('shifts.edit', $shift));

        $response->assertStatus(200);
    }

    public function test_update_modifies_shift(): void
    {
        $shift = Shift::factory()->create(['name' => 'Shift Pagi', 'code' => 'P']);

        $updateData = [
            'name' => 'Shift Pagi Updated',
            'code' => 'P',
            'start_time' => '07:00',
            'end_time' => '15:00',
            'status' => 'Nonaktif',
        ];

        $response = $this->actingAs($this->user)->put(route('shifts.update', $shift), $updateData);

        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'name' => 'Shift Pagi Updated',
            'status' => 'Nonaktif',
        ]);
        $response->assertRedirect(route('shifts.index'));
    }

    public function test_destroy_deletes_shift(): void
    {
        $shift = Shift::factory()->create();

        $response = $this->actingAs($this->user)->delete(route('shifts.destroy', $shift));

        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
        $response->assertRedirect(route('shifts.index'));
    }
}
