<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function pageProps($response): array
    {
        $page = json_decode(json_encode($response->viewData('page')), true);

        return $page['props'];
    }

    public function test_create_form_receives_shift_options(): void
    {
        $shift = Shift::factory()->create(['name' => 'Shift Pagi']);

        $response = $this->actingAs($this->user)->get(route('employees.create'));

        $response->assertStatus(200);
        $shifts = $this->pageProps($response)['shifts'];

        $this->assertCount(1, $shifts);
        $this->assertSame($shift->name, $shifts[0]['name']);
    }

    public function test_store_persists_shift_id(): void
    {
        $shift = Shift::factory()->create();

        $data = [
            'name' => 'Andi Saputra',
            'status' => 'Aktif',
            'shift_id' => $shift->id,
        ];

        $response = $this->actingAs($this->user)->post(route('employees.store'), $data);

        $this->assertDatabaseHas('employees', ['name' => 'Andi Saputra', 'shift_id' => $shift->id]);
        $response->assertRedirect(route('employees.index'));
    }

    public function test_store_allows_null_shift_id(): void
    {
        $data = [
            'name' => 'Budi Santoso',
            'status' => 'Aktif',
        ];

        $response = $this->actingAs($this->user)->post(route('employees.store'), $data);

        $this->assertDatabaseHas('employees', ['name' => 'Budi Santoso', 'shift_id' => null]);
        $response->assertRedirect(route('employees.index'));
    }

    public function test_update_changes_shift_id(): void
    {
        $oldShift = Shift::factory()->create();
        $newShift = Shift::factory()->create();
        $employee = Employee::factory()->create(['shift_id' => $oldShift->id]);

        $response = $this->actingAs($this->user)->put(route('employees.update', $employee), [
            'name' => $employee->name,
            'status' => $employee->status,
            'shift_id' => $newShift->id,
        ]);

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'shift_id' => $newShift->id]);
        $response->assertRedirect(route('employees.index'));
    }
}
