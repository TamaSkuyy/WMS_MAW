# Shift Master Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a "Shift" master-data entity (name, code, start/end time, status) with full CRUD + Excel/PDF import/export, and link it to Employee via a nullable `shift_id` so each employee can be assigned one fixed shift.

**Architecture:** `Shift` follows the exact pattern already established by `JobPosition`/`WorkLocation`/`Department`: a simple Eloquent model + migration, a controller using the `HasImportExport` trait backed by a `ShiftImporter`/`ShiftExporter` pair, and Inertia/React pages under `resources/js/Pages/Master/Shifts/`. `Employee` gets a new nullable `shift_id` FK (mirroring `job_position_id`), wired into its controller, importer/exporter, and forms exactly like the other three lookup FKs already are. The sidebar entry ships via an idempotent `DB::table('menus')->insert()` migration (the only mechanism that reaches production, since `deploy-production.sh` runs `migrate --force` but never `db:seed`), plus a matching `MenuSeeder.php` entry for fresh dev installs.

**Tech Stack:** Laravel 11 (PHP), PHPUnit feature tests, Inertia.js + React (TypeScript), existing `App\Services\ImportExport` layer (`maatwebsite/excel` + `barryvdh/laravel-dompdf`).

## Global Constraints

- Follow the `JobPosition` pattern exactly: table `shifts`, controller `ShiftController` with `HasImportExport`, `Route::resource('shifts', ...)->except(['show'])`.
- `end_time` may be earlier than `start_time` (overnight shift, e.g. 22:00–06:00) — never add a cross-field ordering validation rule between them.
- `name` and `code` are both unique on `shifts` (DB-level `unique()` + validation `unique:shifts`).
- Employee ↔ Shift is a single nullable `shift_id` column on `employees` (one fixed shift per employee) — no per-date scheduling, no pivot table.
- Time fields use Laravel validation `date_format:H:i` (matches the `<input type="time">` value the frontend sends).
- Icon for the new menu entry must be a name actually registered in `resources/js/Tailadmin/components/IconMapper.tsx`'s `iconsMap` (`BoxCubeIcon`, `CalenderIcon`, `ChevronDownIcon`, `GridIcon`, `HorizontaLDots`, `ListIcon`, `PageIcon`, `PieChartIcon`, `PlugInIcon`, `TableIcon`, `UserCircleIcon`) — use `CalenderIcon` (closest to a schedule/time concept); an unregistered name silently falls back to `BoxCubeIcon`.
- The `menus` table's "Master Data" parent row is created only by `database/seeders/MenuSeeder.php`, which is **never run automatically in production** (`deploy-production.sh` only runs `php artisan migrate --force`). The Shift menu migration must not assume "Master Data" already exists — it must look it up and, if missing (fresh install / test DB), create it itself with the same fields `MenuSeeder.php` uses, guarded so it's not duplicated if the seeder already created it.
- Follow existing test conventions: PHPUnit class-based tests (`extends Tests\TestCase`, `use RefreshDatabase`), one feature test class per controller/entity under `tests/Feature/`, import/export tests under `tests/Feature/ImportExport/`.
- Run backend tests with `php artisan test tests/Feature/<File>.php`. Run frontend verification with `npm run build`.
- Spec reference: `docs/superpowers/specs/2026-07-13-shift-master-design.md`

---

## File Structure

### Files to Create

```
database/migrations/2026_07_13_120000_create_shifts_table.php            [shifts table schema]
database/migrations/2026_07_13_120100_add_shift_id_to_employees_table.php [employees.shift_id FK]
database/migrations/2026_07_13_120200_add_shift_menu.php                 [idempotent menus row insert]
app/Models/Shift.php                                                     [Shift model]
database/factories/ShiftFactory.php                                      [Shift factory for tests]
app/Http/Controllers/ShiftController.php                                 [Shift CRUD + import/export]
app/Services/ImportExport/Imports/ShiftImporter.php                      [BaseImporter for Shift]
app/Services/ImportExport/Exports/ShiftExporter.php                      [BaseExporter for Shift]
resources/js/Pages/Master/Shifts/Index.tsx                               [Shift list page]
resources/js/Pages/Master/Shifts/Create.tsx                              [Shift create form]
resources/js/Pages/Master/Shifts/Edit.tsx                                [Shift edit form]
tests/Feature/ShiftControllerTest.php                                    [Shift CRUD tests]
tests/Feature/ImportExport/ShiftImportExportTest.php                     [Shift import/export tests]
```

### Files to Modify

```
routes/web.php                                          [Shift resource + import/export routes]
app/Models/Employee.php                                 [shift_id fillable + shift() relation]
app/Http/Controllers/EmployeeController.php              [accept/display shift_id]
app/Services/ImportExport/Imports/EmployeeImporter.php   [resolve Shift by name]
app/Services/ImportExport/Exports/EmployeeExporter.php   [export Shift column]
resources/js/Pages/Master/Employees/Create.tsx           [Shift select field]
resources/js/Pages/Master/Employees/Edit.tsx             [Shift select field]
resources/js/Pages/Master/Employees/Index.tsx            [Shift column + import field]
resources/js/Pages/Master/Employees/Show.tsx             [Shift detail field]
tests/Feature/ImportExport/EmployeeImportExportTest.php  [Shift import resolution tests]
database/seeders/MenuSeeder.php                          [Shift entry under Master Data]
```

---

## Task 1: Shift database schema, model, and Employee relation

**Files:**
- Create: `database/migrations/2026_07_13_120000_create_shifts_table.php`
- Create: `database/migrations/2026_07_13_120100_add_shift_id_to_employees_table.php`
- Create: `app/Models/Shift.php`
- Create: `database/factories/ShiftFactory.php`
- Modify: `app/Models/Employee.php`
- Test: `tests/Feature/ShiftControllerTest.php`

**Interfaces:**
- Produces: `App\Models\Shift` — `fillable = ['name', 'code', 'start_time', 'end_time', 'status', 'created_by', 'updated_by']`, `employees(): HasMany`, `scopeActive($query)`.
- Produces: `App\Models\Employee::shift(): BelongsTo` and `shift_id` added to `Employee::$fillable`.
- Produces: `Shift::factory()` — default state usable by later tasks/tests.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ShiftControllerTest.php`:

```php
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
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/ShiftControllerTest.php`
Expected: FAIL — `Class "App\Models\Shift" not found`.

- [ ] **Step 3: Create the shifts table migration**

Create `database/migrations/2026_07_13_120000_create_shifts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 10)->unique();
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status')->default('Aktif');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
```

- [ ] **Step 4: Create the employees.shift_id migration**

Create `database/migrations/2026_07_13_120100_add_shift_id_to_employees_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('department_id')
                ->constrained('shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });
    }
};
```

- [ ] **Step 5: Create the Shift model**

Create `app/Models/Shift.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'start_time', 'end_time', 'status', 'created_by', 'updated_by',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Aktif');
    }
}
```

- [ ] **Step 6: Update the Employee model**

In `app/Models/Employee.php`, add `'shift_id'` to `$fillable` and add a `shift()` relation:

```php
    protected $fillable = [
        'name', 'nik', 'job_position_id', 'work_location_id',
        'department_id', 'shift_id', 'user_id', 'phone', 'email', 'status',
        'created_by', 'updated_by',
    ];
```

Add this method alongside the other `belongsTo` relations (e.g. right after `department()`):

```php
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
```

- [ ] **Step 7: Create the Shift factory**

Create `database/factories/ShiftFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Shift ' . fake()->unique()->word(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'Aktif',
        ];
    }
}
```

- [ ] **Step 8: Run the test to verify it passes**

Run: `php artisan test tests/Feature/ShiftControllerTest.php`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_07_13_120000_create_shifts_table.php database/migrations/2026_07_13_120100_add_shift_id_to_employees_table.php app/Models/Shift.php app/Models/Employee.php database/factories/ShiftFactory.php tests/Feature/ShiftControllerTest.php
git commit -m "feat: add shifts table and employees.shift_id relation"
```

---

## Task 2: Shift master CRUD, import/export, and frontend pages

**Files:**
- Create: `app/Http/Controllers/ShiftController.php`
- Create: `app/Services/ImportExport/Imports/ShiftImporter.php`
- Create: `app/Services/ImportExport/Exports/ShiftExporter.php`
- Create: `resources/js/Pages/Master/Shifts/Index.tsx`
- Create: `resources/js/Pages/Master/Shifts/Create.tsx`
- Create: `resources/js/Pages/Master/Shifts/Edit.tsx`
- Modify: `routes/web.php`
- Modify (extend): `tests/Feature/ShiftControllerTest.php`
- Test: `tests/Feature/ImportExport/ShiftImportExportTest.php`

**Interfaces:**
- Consumes: `App\Models\Shift`, `Shift::factory()` from Task 1.
- Produces: routes `shifts.index`, `shifts.create`, `shifts.store`, `shifts.edit`, `shifts.update`, `shifts.destroy`, `shifts.import`, `shifts.import.preview`, `shifts.export`, `shifts.import-template`.
- Produces: Inertia props for `Master/Shifts/Index` (`shifts` paginated, `filters`), `Master/Shifts/Edit` (`shift`).

- [ ] **Step 1: Replace `tests/Feature/ShiftControllerTest.php` with the full CRUD test suite**

Replace the entire file with (keeps Task 1's relation test, adds CRUD coverage):

```php
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
```

- [ ] **Step 2: Run the tests to verify the new ones fail**

Run: `php artisan test tests/Feature/ShiftControllerTest.php`
Expected: `test_employee_can_be_assigned_a_shift` PASSES, all other tests FAIL (route `shifts.index` etc. not defined).

- [ ] **Step 3: Create the ShiftImporter**

Create `app/Services/ImportExport/Imports/ShiftImporter.php`:

```php
<?php

namespace App\Services\ImportExport\Imports;

use App\Models\Shift;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class ShiftImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return Shift::class;
    }

    public function uniqueKey(): string|array
    {
        return 'code';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:10'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'status' => ['required', 'string', 'in:Aktif,Nonaktif'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Nama', 'Kode', 'Jam Mulai', 'Jam Selesai', 'Status'];
    }

    public function fixedFields(int $userId): array
    {
        return [
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    public function transformRow(array $mapped): array
    {
        $mapped['status'] = $mapped['status'] ?: 'Aktif';

        return $mapped;
    }
}
```

- [ ] **Step 4: Create the ShiftExporter**

Create `app/Services/ImportExport/Exports/ShiftExporter.php`:

```php
<?php

namespace App\Services\ImportExport\Exports;

use App\Models\Shift;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class ShiftExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Nama', 'Kode', 'Jam Mulai', 'Jam Selesai', 'Status'];
    }

    public function exportQuery(): Builder
    {
        return Shift::query()->orderBy('name');
    }

    public function mapRow($model): array
    {
        return [$model->name, $model->code, $model->start_time, $model->end_time, $model->status];
    }
}
```

- [ ] **Step 5: Create the ShiftController**

Create `app/Http/Controllers/ShiftController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\Shift;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\ShiftExporter;
use App\Services\ImportExport\Imports\ShiftImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShiftController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new ShiftImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new ShiftExporter();
    }

    protected function exportFileName(): string
    {
        return 'shifts-export';
    }

    public function index(Request $request)
    {
        return Inertia::render('Master/Shifts/Index', [
            'shifts' => Shift::orderBy('name')
                ->when($request->search, function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%")
                          ->orWhere('code', 'like', "%{$search}%");
                })
                ->paginate(10)
                ->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Master/Shifts/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:shifts',
            'code' => 'required|string|max:10|unique:shifts',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'status' => 'required|string|in:Aktif,Nonaktif',
        ]);

        $validated['created_by'] = auth()->id();

        Shift::create($validated);

        return redirect()->route('shifts.index')->with('success', 'Shift berhasil dibuat.');
    }

    public function edit(Shift $shift)
    {
        return Inertia::render('Master/Shifts/Edit', [
            'shift' => $shift,
        ]);
    }

    public function update(Request $request, Shift $shift)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:shifts,name,' . $shift->id,
            'code' => 'required|string|max:10|unique:shifts,code,' . $shift->id,
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'status' => 'required|string|in:Aktif,Nonaktif',
        ]);

        $validated['updated_by'] = auth()->id();

        $shift->update($validated);

        return redirect()->route('shifts.index')->with('success', 'Shift berhasil diupdate.');
    }

    public function destroy(Shift $shift)
    {
        $shift->delete();

        return redirect()->route('shifts.index')->with('success', 'Shift berhasil dihapus.');
    }
}
```

- [ ] **Step 6: Add the Shift routes**

In `routes/web.php`, add the import alongside the other controller `use` statements near the top:

```php
use App\Http\Controllers\ShiftController;
```

Then, inside the `Route::middleware('auth')->group(function () { ... })` block, immediately after the `Route::resource('employees', EmployeeController::class);` line, add:

```php
    Route::post('shifts/import/preview', [ShiftController::class, 'importPreview'])->name('shifts.import.preview');
    Route::post('shifts/import', [ShiftController::class, 'import'])->name('shifts.import');
    Route::get('shifts/export', [ShiftController::class, 'export'])->name('shifts.export');
    Route::get('shifts/import-template', [ShiftController::class, 'importTemplate'])->name('shifts.import-template');
    Route::resource('shifts', ShiftController::class)->except(['show']);
```

- [ ] **Step 7: Create the Shift Index page**

Create `resources/js/Pages/Master/Shifts/Index.tsx`:

```tsx
import React, { useState } from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import SearchInput from '../../../Tailadmin/components/form/input/SearchInput';
import TableActions from '../../../Tailadmin/components/common/TableActions';
import EmptyState from '../../../Tailadmin/components/common/EmptyState';
import ImportExportToolbar from '../../../Components/ImportExport/ImportExportToolbar';
import ImportModal from '../../../Components/ImportExport/ImportModal';

export default function Index({ shifts, filters }: any) {
    const [importModalOpen, setImportModalOpen] = useState(false);

    const handleDelete = (id: number) => {
        if (confirm('Hapus shift ini?')) {
            router.delete(route('shifts.destroy', id));
        }
    };

    return (
        <AppLayout>
            <Head title="Shift" />
            <PageBreadcrumb pageTitle="Shift" />
            <ComponentCard title="Daftar Shift">
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <Link href={route('shifts.create')}><Button>Tambah Shift</Button></Link>
                    <SearchInput
                        placeholder="Cari nama atau kode shift..."
                        routeName="shifts.index"
                        filters={filters}
                    />
                    <ImportExportToolbar
                        importUrl={route('shifts.import')}
                        previewUrl={route('shifts.import.preview')}
                        exportUrl={route('shifts.export')}
                        onImportClick={() => setImportModalOpen(true)}
                    />
                </div>
                <ImportModal
                    isOpen={importModalOpen}
                    onClose={() => setImportModalOpen(false)}
                    onComplete={() => window.location.reload()}
                    importUrl={route('shifts.import')}
                    previewUrl={route('shifts.import.preview')}
                    templateUrl={route('shifts.import-template')}
                    title="Shift"
                    fields={[
                        { key: 'name', label: 'Nama', required: true },
                        { key: 'code', label: 'Kode', required: true },
                        { key: 'start_time', label: 'Jam Mulai', required: true },
                        { key: 'end_time', label: 'Jam Selesai', required: true },
                        { key: 'status', label: 'Status (Aktif/Nonaktif)', required: true },
                    ]}
                />
                {shifts.data.length === 0 ? (
                    <EmptyState
                        icon="🕒"
                        title="Belum ada shift"
                        message="Tambahkan shift kerja seperti Pagi, Siang, atau Malam."
                        actionLabel="Tambah Shift"
                        actionRoute={route('shifts.create')}
                    />
                ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full">
                        <thead className="bg-[#F8F9FC] border-b border-[#E9ECEF]">
                            <tr>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Nama</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Kode</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Jam Mulai</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Jam Selesai</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Status</th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {shifts.data.map((s: any) => (
                                <tr key={s.id} className="border-b border-[#F1F3F5] hover:bg-[#F8F9FC] transition-all duration-150">
                                    <td className="px-4 py-3 text-sm text-[#1A1D23] font-medium">{s.name}</td>
                                    <td className="px-4 py-3 text-[13px] text-[#6C757D]">{s.code}</td>
                                    <td className="px-4 py-3 text-[13px] text-[#6C757D]">{s.start_time}</td>
                                    <td className="px-4 py-3 text-[13px] text-[#6C757D]">{s.end_time}</td>
                                    <td className="px-4 py-3 text-sm">
                                        <span className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${s.status === 'Aktif' ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400'}`}>
                                            {s.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-[#1A1D23]">
                                        <TableActions
                                            editRoute={route('shifts.edit', s.id)}
                                            onDelete={() => handleDelete(s.id)}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                )}
                {shifts.total > shifts.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">Menampilkan {shifts.from} sampai {shifts.to} dari {shifts.total}</div>
                        <div className="flex gap-2">
                            {shifts.prev_page_url ? <Link href={shifts.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Sebelumnya</Link> : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Sebelumnya</span>}
                            <span className="px-3 py-1 text-sm">Halaman {shifts.current_page} dari {shifts.last_page}</span>
                            {shifts.next_page_url ? <Link href={shifts.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Berikutnya</Link> : <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Berikutnya</span>}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </AppLayout>
    );
}
```

- [ ] **Step 8: Create the Shift Create page**

Create `resources/js/Pages/Master/Shifts/Create.tsx`:

```tsx
import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeftIcon, CheckIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';
import Select from '../../../Tailadmin/components/form/Select';
import { Link } from '@inertiajs/react';

export default function Create() {
    const { data, setData, post, errors } = useForm({
        name: '',
        code: '',
        start_time: '',
        end_time: '',
        status: 'Aktif',
    });

    const statusOptions = [
        { value: 'Aktif', label: 'Aktif' },
        { value: 'Nonaktif', label: 'Nonaktif' },
    ];

    return (
        <AppLayout>
            <Head title="Tambah Shift" />
            <PageBreadcrumb pageTitle="Tambah Shift" />

            <div className="max-w-2xl">
                <ComponentCard
                    title="Shift Baru"
                    desc="Tambahkan shift kerja baru"
                    action={
                        <Link href={route('shifts.index')}>
                            <Button variant="outline" size="sm" icon={<ArrowLeftIcon className="w-4 h-4" />}>Kembali</Button>
                        </Link>
                    }
                >
                    <form onSubmit={(e) => { e.preventDefault(); post(route('shifts.store')); }} className="space-y-5">
                        <div>
                            <Label>Nama *</Label>
                            <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="contoh: Shift Pagi" />
                            {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                        </div>
                        <div>
                            <Label>Kode *</Label>
                            <Input type="text" value={data.code} onChange={(e) => setData('code', e.target.value)} placeholder="contoh: P" />
                            {errors.code && <p className="mt-1 text-sm text-red-500">{errors.code}</p>}
                        </div>
                        <div>
                            <Label>Jam Mulai *</Label>
                            <Input type="time" value={data.start_time} onChange={(e) => setData('start_time', e.target.value)} />
                            {errors.start_time && <p className="mt-1 text-sm text-red-500">{errors.start_time}</p>}
                        </div>
                        <div>
                            <Label>Jam Selesai *</Label>
                            <Input type="time" value={data.end_time} onChange={(e) => setData('end_time', e.target.value)} />
                            {errors.end_time && <p className="mt-1 text-sm text-red-500">{errors.end_time}</p>}
                            <p className="mt-1 text-xs text-[#6C757D]">Untuk shift malam, jam selesai boleh lebih kecil dari jam mulai (contoh: 22:00 - 06:00).</p>
                        </div>
                        <div>
                            <Label>Status</Label>
                            <Select options={statusOptions} placeholder="Pilih Status" defaultValue={data.status} onChange={(val) => setData('status', val)} />
                            {errors.status && <p className="mt-1 text-sm text-red-500">{errors.status}</p>}
                        </div>
                        <div className="flex gap-3 pt-4 border-t border-[#F1F3F5]">
                            <Button type="submit" icon={<CheckIcon className="w-4 h-4" />}>Simpan</Button>
                            <Button type="button" variant="outline" onClick={() => window.history.back()}>Batal</Button>
                        </div>
                    </form>
                </ComponentCard>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 9: Create the Shift Edit page**

Create `resources/js/Pages/Master/Shifts/Edit.tsx`:

```tsx
import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeftIcon, CheckIcon } from '@heroicons/react/24/outline';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';
import Select from '../../../Tailadmin/components/form/Select';
import { Link } from '@inertiajs/react';

export default function Edit({ shift }: any) {
    const { data, setData, put, errors } = useForm({
        name: shift.name,
        code: shift.code,
        start_time: shift.start_time?.slice(0, 5) || '',
        end_time: shift.end_time?.slice(0, 5) || '',
        status: shift.status,
    });

    const statusOptions = [
        { value: 'Aktif', label: 'Aktif' },
        { value: 'Nonaktif', label: 'Nonaktif' },
    ];

    return (
        <AppLayout>
            <Head title={`Edit Shift - ${shift.name}`} />
            <PageBreadcrumb pageTitle={`Edit: ${shift.name}`} />

            <div className="max-w-2xl">
                <ComponentCard
                    title="Edit Shift"
                    desc="Perbarui data shift"
                    action={
                        <Link href={route('shifts.index')}>
                            <Button variant="outline" size="sm" icon={<ArrowLeftIcon className="w-4 h-4" />}>Kembali</Button>
                        </Link>
                    }
                >
                    <form onSubmit={(e) => { e.preventDefault(); put(route('shifts.update', shift.id)); }} className="space-y-5">
                        <div>
                            <Label>Nama *</Label>
                            <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                        </div>
                        <div>
                            <Label>Kode *</Label>
                            <Input type="text" value={data.code} onChange={(e) => setData('code', e.target.value)} />
                            {errors.code && <p className="mt-1 text-sm text-red-500">{errors.code}</p>}
                        </div>
                        <div>
                            <Label>Jam Mulai *</Label>
                            <Input type="time" value={data.start_time} onChange={(e) => setData('start_time', e.target.value)} />
                            {errors.start_time && <p className="mt-1 text-sm text-red-500">{errors.start_time}</p>}
                        </div>
                        <div>
                            <Label>Jam Selesai *</Label>
                            <Input type="time" value={data.end_time} onChange={(e) => setData('end_time', e.target.value)} />
                            {errors.end_time && <p className="mt-1 text-sm text-red-500">{errors.end_time}</p>}
                            <p className="mt-1 text-xs text-[#6C757D]">Untuk shift malam, jam selesai boleh lebih kecil dari jam mulai (contoh: 22:00 - 06:00).</p>
                        </div>
                        <div>
                            <Label>Status</Label>
                            <Select options={statusOptions} placeholder="Pilih Status" defaultValue={data.status} onChange={(val) => setData('status', val)} />
                            {errors.status && <p className="mt-1 text-sm text-red-500">{errors.status}</p>}
                        </div>
                        <div className="flex gap-3 pt-4 border-t border-[#F1F3F5]">
                            <Button type="submit" icon={<CheckIcon className="w-4 h-4" />}>Simpan</Button>
                            <Button type="button" variant="outline" onClick={() => window.history.back()}>Batal</Button>
                        </div>
                    </form>
                </ComponentCard>
            </div>
        </AppLayout>
    );
}
```

Note: `shift.start_time`/`shift.end_time` come back from MySQL's `TIME` column as `"HH:MM:SS"`; `.slice(0, 5)` trims to `"HH:MM"` so the `<input type="time">` pre-fills correctly.

- [ ] **Step 10: Run the Shift CRUD tests to verify they pass**

Run: `php artisan test tests/Feature/ShiftControllerTest.php`
Expected: PASS (all 11 tests)

- [ ] **Step 11: Write the import/export tests**

Create `tests/Feature/ImportExport/ShiftImportExportTest.php`:

```php
<?php

namespace Tests\Feature\ImportExport;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ShiftImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_xlsx_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Shift::factory()->create();

        $response = $this->get(route('shifts.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_export_pdf_returns_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Shift::factory()->create();

        $response = $this->get(route('shifts.export', ['format' => 'pdf']));

        $response->assertOk();
    }

    public function test_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('shifts.import-template', ['format' => 'csv']));

        $response->assertOk();
    }

    public function test_import_creates_valid_rows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'shifts.csv',
            "Nama,Kode,Jam Mulai,Jam Selesai,Status\nShift Pagi,P,08:00,16:00,Aktif"
        );

        $response = $this->post(route('shifts.import'), [
            'file' => $file,
            'column_mapping' => [
                'name' => 'Nama',
                'code' => 'Kode',
                'start_time' => 'Jam Mulai',
                'end_time' => 'Jam Selesai',
                'status' => 'Status',
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('shifts', ['name' => 'Shift Pagi', 'code' => 'P']);
    }

    public function test_import_skips_duplicate_code(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Shift::factory()->create(['name' => 'Shift Pagi', 'code' => 'P']);

        $file = UploadedFile::fake()->createWithContent(
            'shifts.csv',
            "Nama,Kode,Jam Mulai,Jam Selesai,Status\nShift Pagi Lain,P,09:00,17:00,Aktif"
        );

        $response = $this->post(route('shifts.import'), [
            'file' => $file,
            'column_mapping' => [
                'name' => 'Nama',
                'code' => 'Kode',
                'start_time' => 'Jam Mulai',
                'end_time' => 'Jam Selesai',
                'status' => 'Status',
            ],
        ]);

        $importLogId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', ['id' => $importLogId, 'skipped_rows' => 1]);
    }
}
```

- [ ] **Step 12: Run the import/export tests to verify they pass**

Run: `php artisan test tests/Feature/ImportExport/ShiftImportExportTest.php`
Expected: PASS (all 5 tests)

- [ ] **Step 13: Build the frontend to verify no type/syntax errors**

Run: `npm run build`
Expected: build succeeds with no errors mentioning `Master/Shifts/`.

- [ ] **Step 14: Commit**

```bash
git add app/Http/Controllers/ShiftController.php app/Services/ImportExport/Imports/ShiftImporter.php app/Services/ImportExport/Exports/ShiftExporter.php resources/js/Pages/Master/Shifts routes/web.php tests/Feature/ShiftControllerTest.php tests/Feature/ImportExport/ShiftImportExportTest.php
git commit -m "feat: add Shift master CRUD, import/export, and frontend pages"
```

---

## Task 3: Employee ↔ Shift integration

**Files:**
- Modify: `app/Http/Controllers/EmployeeController.php`
- Modify: `app/Services/ImportExport/Imports/EmployeeImporter.php`
- Modify: `app/Services/ImportExport/Exports/EmployeeExporter.php`
- Modify: `resources/js/Pages/Master/Employees/Create.tsx`
- Modify: `resources/js/Pages/Master/Employees/Edit.tsx`
- Modify: `resources/js/Pages/Master/Employees/Index.tsx`
- Modify: `resources/js/Pages/Master/Employees/Show.tsx`
- Test: Create `tests/Feature/EmployeeControllerTest.php`
- Test: Modify `tests/Feature/ImportExport/EmployeeImportExportTest.php`

**Interfaces:**
- Consumes: `App\Models\Shift`, `Shift::factory()` from Task 1; `shifts.*` routes from Task 2.
- Produces: `EmployeeController::create()`/`edit()` now also pass `shifts` (`Shift::orderBy('name')->get(['id','name'])`); `store()`/`update()` accept `shift_id` (nullable, `exists:shifts,id`); `index()`/`show()` eager-load `shift`.

- [ ] **Step 1: Write the failing controller test**

Create `tests/Feature/EmployeeControllerTest.php`:

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/EmployeeControllerTest.php`
Expected: FAIL — `test_create_form_receives_shift_options` fails (`shifts` prop missing), `test_store_persists_shift_id`/`test_update_changes_shift_id` fail (`shift_id` not in `$validated`, column stays `null`).

- [ ] **Step 3: Update EmployeeController**

Replace the full contents of `app/Http/Controllers/EmployeeController.php` with:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\EmployeeExporter;
use App\Services\ImportExport\Imports\EmployeeImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new EmployeeImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new EmployeeExporter();
    }

    protected function exportFileName(): string
    {
        return 'employees-export';
    }

    public function index(Request $request)
    {
        return Inertia::render('Master/Employees/Index', [
            'employees' => Employee::with(['jobPosition', 'workLocation', 'department', 'shift', 'user'])
                ->orderBy('name')
                ->when($request->search, function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%")
                          ->orWhere('nik', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                })
                ->paginate(10)
                ->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Master/Employees/Create', [
            'jobPositions' => JobPosition::orderBy('name')->get(['id', 'name']),
            'workLocations' => WorkLocation::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'shifts' => Shift::orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')
                ->whereDoesntHave('employee')
                ->orWhereNull('employee_id')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'nik' => 'nullable|string|max:50|unique:employees',
            'job_position_id' => 'nullable|exists:job_positions,id',
            'work_location_id' => 'nullable|exists:work_locations,id',
            'department_id' => 'nullable|exists:departments,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'user_id' => 'nullable|exists:users,id|unique:employees,user_id',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'status' => 'required|string|in:Aktif,Nonaktif',
        ]);

        $validated['created_by'] = auth()->id();

        Employee::create($validated);

        return redirect()->route('employees.index')->with('success', 'Karyawan berhasil dibuat.');
    }

    public function show(Employee $employee)
    {
        return Inertia::render('Master/Employees/Show', [
            'employee' => $employee->load(['jobPosition', 'workLocation', 'department', 'shift', 'user', 'creator', 'updater']),
        ]);
    }

    public function edit(Employee $employee)
    {
        return Inertia::render('Master/Employees/Edit', [
            'employee' => $employee,
            'jobPositions' => JobPosition::orderBy('name')->get(['id', 'name']),
            'workLocations' => WorkLocation::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'shifts' => Shift::orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')
                ->whereDoesntHave('employee')
                ->orWhere('employee_id', $employee->user_id)
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'nik' => 'nullable|string|max:50|unique:employees,nik,' . $employee->id,
            'job_position_id' => 'nullable|exists:job_positions,id',
            'work_location_id' => 'nullable|exists:work_locations,id',
            'department_id' => 'nullable|exists:departments,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'user_id' => 'nullable|exists:users,id|unique:employees,user_id,' . $employee->id,
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'status' => 'required|string|in:Aktif,Nonaktif',
        ]);

        $validated['updated_by'] = auth()->id();

        $employee->update($validated);

        return redirect()->route('employees.index')->with('success', 'Karyawan berhasil diupdate.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Karyawan berhasil dihapus.');
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/EmployeeControllerTest.php`
Expected: PASS (all 4 tests)

- [ ] **Step 5: Add the Shift select to the Employee Create page**

In `resources/js/Pages/Master/Employees/Create.tsx`:

Change the function signature and initial form data:

```tsx
export default function Create({ jobPositions, workLocations, departments, shifts, users }: any) {
    const { data, setData, post, errors } = useForm({
        name: '',
        nik: '',
        job_position_id: '',
        work_location_id: '',
        department_id: '',
        shift_id: '',
        user_id: '',
        phone: '',
        email: '',
        status: 'Aktif',
    });
```

Insert this block right after the existing "Departemen" `<div>` block (after its closing `</div>`, before the "User / Login" block):

```tsx
                        <div>
                            <Label>Shift</Label>
                            <SearchableSelect
                                options={shifts.map((s: any) => ({ value: s.id, label: s.name }))}
                                value={data.shift_id}
                                onChange={(val) => setData('shift_id', val)}
                                placeholder="Cari shift..."
                            />
                            {errors.shift_id && <p className="mt-1 text-sm text-red-500">{errors.shift_id}</p>}
                        </div>
```

- [ ] **Step 6: Add the Shift select to the Employee Edit page**

In `resources/js/Pages/Master/Employees/Edit.tsx`:

Change the function signature and initial form data:

```tsx
export default function Edit({ employee, jobPositions, workLocations, departments, shifts, users }: any) {
    const { data, setData, put, errors } = useForm({
        name: employee.name,
        nik: employee.nik || '',
        job_position_id: employee.job_position_id || '',
        work_location_id: employee.work_location_id || '',
        department_id: employee.department_id || '',
        shift_id: employee.shift_id || '',
        user_id: employee.user_id || '',
        phone: employee.phone || '',
        email: employee.email || '',
        status: employee.status,
    });
```

Insert the same Shift `SearchableSelect` block as Step 5, in the same position (right after "Departemen", before "User / Login").

- [ ] **Step 7: Add the Shift column to the Employee Index page**

In `resources/js/Pages/Master/Employees/Index.tsx`:

Add a table header after the "Departemen" `<th>`:

```tsx
                                <th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Shift</th>
```

Add the matching cell after the department `<td>`:

```tsx
                                    <td className="px-4 py-3 text-[13px] text-[#6C757D]">{e.shift?.name || '-'}</td>
```

Add `{ key: 'shift', label: 'Shift', required: false }` to the `ImportModal`'s `fields` array, right after the `department` entry:

```tsx
                        { key: 'shift', label: 'Shift', required: false },
```

- [ ] **Step 8: Add the Shift field to the Employee Show page**

In `resources/js/Pages/Master/Employees/Show.tsx`, insert this block right after the "Departemen" `<div>` block (before "Telepon"):

```tsx
                            <div>
                                <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Shift</dt>
                                <dd className="text-sm text-[#1A1D23]">{employee.shift?.name || '-'}</dd>
                            </div>
```

- [ ] **Step 9: Build the frontend to verify no type/syntax errors**

Run: `npm run build`
Expected: build succeeds with no errors mentioning `Master/Employees/`.

- [ ] **Step 10: Write the failing import test for Shift resolution**

In `tests/Feature/ImportExport/EmployeeImportExportTest.php`, add `use App\Models\Shift;` to the imports, then replace `test_import_resolves_optional_relations_and_stamps_audit_fields` and `test_import_leaves_relation_null_when_optional_lookup_not_found` with:

```php
    public function test_import_resolves_optional_relations_and_stamps_audit_fields(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $position = JobPosition::factory()->create(['name' => 'Staff Gudang']);
        $location = WorkLocation::factory()->create(['name' => 'Gudang Utama']);
        $department = Department::factory()->create(['name' => 'Logistik']);
        $shift = Shift::factory()->create(['name' => 'Shift Pagi']);

        $file = UploadedFile::fake()->createWithContent(
            'employees.csv',
            "Nama,NIK,Jabatan,Lokasi,Departemen,Shift,Telepon,Email,Status\n"
            . "Andi Saputra,NIK-0001,Staff Gudang,Gudang Utama,Logistik,Shift Pagi,08123456789,andi@wms.test,Aktif"
        );

        $response = $this->post(route('employees.import'), [
            'file' => $file,
            'column_mapping' => [
                'name' => 'Nama',
                'nik' => 'NIK',
                'job_position' => 'Jabatan',
                'work_location' => 'Lokasi',
                'department' => 'Departemen',
                'shift' => 'Shift',
                'phone' => 'Telepon',
                'email' => 'Email',
                'status' => 'Status',
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('employees', [
            'nik' => 'NIK-0001',
            'job_position_id' => $position->id,
            'work_location_id' => $location->id,
            'department_id' => $department->id,
            'shift_id' => $shift->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    public function test_import_leaves_relation_null_when_optional_lookup_not_found(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'employees.csv',
            "Nama,NIK,Jabatan,Lokasi,Departemen,Shift,Telepon,Email,Status\n"
            . "Budi Santoso,NIK-0002,Jabatan Tidak Ada,,,,08123456780,budi@wms.test,Aktif"
        );

        $response = $this->post(route('employees.import'), [
            'file' => $file,
            'column_mapping' => [
                'name' => 'Nama',
                'nik' => 'NIK',
                'job_position' => 'Jabatan',
                'work_location' => 'Lokasi',
                'department' => 'Departemen',
                'shift' => 'Shift',
                'phone' => 'Telepon',
                'email' => 'Email',
                'status' => 'Status',
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('employees', [
            'nik' => 'NIK-0002',
            'job_position_id' => null,
            'work_location_id' => null,
            'department_id' => null,
            'shift_id' => null,
        ]);
    }
```

- [ ] **Step 11: Run the import tests to verify they fail**

Run: `php artisan test tests/Feature/ImportExport/EmployeeImportExportTest.php`
Expected: FAIL — `shift_id` assertions fail (column stays `null` because `EmployeeImporter` doesn't resolve it yet).

- [ ] **Step 12: Update EmployeeImporter**

Replace the full contents of `app/Services/ImportExport/Imports/EmployeeImporter.php` with:

```php
<?php

namespace App\Services\ImportExport\Imports;

use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\Shift;
use App\Models\WorkLocation;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Contracts\Importable;

class EmployeeImporter extends BaseImporter implements Importable
{
    public function modelType(): string
    {
        return Employee::class;
    }

    public function uniqueKey(): string|array
    {
        return 'nik';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:50'],
            'job_position_id' => ['nullable', 'exists:job_positions,id'],
            'work_location_id' => ['nullable', 'exists:work_locations,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'string', 'in:Aktif,Nonaktif'],
        ];
    }

    public function templateHeadings(): array
    {
        return ['Nama', 'NIK', 'Jabatan', 'Lokasi', 'Departemen', 'Shift', 'Telepon', 'Email', 'Status'];
    }

    public function fixedFields(int $userId): array
    {
        return [
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    public function transformRow(array $mapped): array
    {
        $mapped['job_position_id'] = $this->resolveForeignKey(JobPosition::class, 'name', $mapped['job_position'] ?? null, required: false);
        $mapped['work_location_id'] = $this->resolveForeignKey(WorkLocation::class, 'name', $mapped['work_location'] ?? null, required: false);
        $mapped['department_id'] = $this->resolveForeignKey(Department::class, 'name', $mapped['department'] ?? null, required: false);
        $mapped['shift_id'] = $this->resolveForeignKey(Shift::class, 'name', $mapped['shift'] ?? null, required: false);
        $mapped['status'] = $mapped['status'] ?: 'Aktif';

        unset($mapped['job_position'], $mapped['work_location'], $mapped['department'], $mapped['shift']);

        return $mapped;
    }
}
```

- [ ] **Step 13: Update EmployeeExporter**

Replace the full contents of `app/Services/ImportExport/Exports/EmployeeExporter.php` with:

```php
<?php

namespace App\Services\ImportExport\Exports;

use App\Models\Employee;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class EmployeeExporter extends BaseExporter
{
    public function headings(): array
    {
        return ['Nama', 'NIK', 'Jabatan', 'Lokasi', 'Departemen', 'Shift', 'Telepon', 'Email', 'Status'];
    }

    public function exportQuery(): Builder
    {
        return Employee::query()->with(['jobPosition', 'workLocation', 'department', 'shift'])->orderBy('name');
    }

    public function mapRow($model): array
    {
        return [
            $model->name,
            $model->nik,
            $model->jobPosition->name ?? '-',
            $model->workLocation->name ?? '-',
            $model->department->name ?? '-',
            $model->shift->name ?? '-',
            $model->phone,
            $model->email,
            $model->status,
        ];
    }
}
```

- [ ] **Step 14: Run the import/export tests to verify they pass**

Run: `php artisan test tests/Feature/ImportExport/EmployeeImportExportTest.php`
Expected: PASS (all 5 tests)

- [ ] **Step 15: Run the full Employee + Shift test suite**

Run: `php artisan test tests/Feature/ShiftControllerTest.php tests/Feature/EmployeeControllerTest.php tests/Feature/ImportExport/ShiftImportExportTest.php tests/Feature/ImportExport/EmployeeImportExportTest.php`
Expected: PASS (all tests)

- [ ] **Step 16: Commit**

```bash
git add app/Http/Controllers/EmployeeController.php app/Services/ImportExport/Imports/EmployeeImporter.php app/Services/ImportExport/Exports/EmployeeExporter.php resources/js/Pages/Master/Employees tests/Feature/EmployeeControllerTest.php tests/Feature/ImportExport/EmployeeImportExportTest.php
git commit -m "feat: link Employee to Shift across forms, import/export, and views"
```

---

## Task 4: Sidebar menu entry

**Files:**
- Create: `database/migrations/2026_07_13_120200_add_shift_menu.php`
- Modify: `database/seeders/MenuSeeder.php`
- Test: `tests/Feature/ShiftMenuMigrationTest.php`

**Interfaces:**
- Consumes: existing `menus` table (created by `2026_05_16_153452_create_menus_table.php`).
- Produces: a `menus` row `name = 'Shift'`, `path = '/shifts'`, nested under the `'Master Data'` parent row (creating that parent row too if it doesn't already exist, e.g. in a fresh test database where `MenuSeeder` was never run).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ShiftMenuMigrationTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShiftMenuMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_menu_exists_under_master_data(): void
    {
        $masterData = DB::table('menus')->where('name', 'Master Data')->first();
        $shiftMenu = DB::table('menus')->where('name', 'Shift')->first();

        $this->assertNotNull($masterData);
        $this->assertNotNull($shiftMenu);
        $this->assertSame($masterData->id, $shiftMenu->parent_id);
        $this->assertSame('/shifts', $shiftMenu->path);
        $this->assertSame('CalenderIcon', $shiftMenu->icon);
    }

    public function test_shift_menu_migration_is_idempotent(): void
    {
        $countBefore = DB::table('menus')->where('name', 'Shift')->count();
        $this->assertSame(1, $countBefore);

        (require database_path('migrations/2026_07_13_120200_add_shift_menu.php'))->up();

        $countAfter = DB::table('menus')->where('name', 'Shift')->count();
        $this->assertSame(1, $countAfter);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/ShiftMenuMigrationTest.php`
Expected: FAIL — no `Shift` row in `menus` (migration doesn't exist yet).

- [ ] **Step 3: Create the menu migration**

Create `database/migrations/2026_07_13_120200_add_shift_menu.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('menus')->where('name', 'Shift')->exists()) {
            return;
        }

        $masterDataId = DB::table('menus')->where('name', 'Master Data')->value('id');

        // MenuSeeder.php normally creates "Master Data", but it never runs
        // automatically in production (deploy only runs `migrate --force`),
        // and a fresh test database won't have it either. Create it here,
        // guarded, so this migration works regardless of seeder state.
        if (! $masterDataId) {
            $masterDataId = DB::table('menus')->insertGetId([
                'name' => 'Master Data',
                'icon' => 'FolderIcon',
                'path' => null,
                'parent_id' => null,
                'sort_order' => 10,
                'group' => 'main',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menus')->insert([
            'name' => 'Shift',
            'icon' => 'CalenderIcon',
            'path' => '/shifts',
            'parent_id' => $masterDataId,
            'sort_order' => 10,
            'group' => 'main',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menus')->where('name', 'Shift')->delete();
    }
};
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/ShiftMenuMigrationTest.php`
Expected: PASS (both tests)

- [ ] **Step 5: Add the matching entry to MenuSeeder.php**

In `database/seeders/MenuSeeder.php`, add this block right after the `'Karyawan'` `Menu::create([...])` call (before the `// === TRANSACTIONS (parent) ===` comment):

```php
        Menu::create([
            'name' => 'Shift',
            'icon' => 'CalenderIcon',
            'path' => '/shifts',
            'parent_id' => $masterData->id,
            'sort_order' => 10,
            'group' => 'main',
        ]);
```

- [ ] **Step 6: Run the full backend test suite to confirm no regressions**

Run: `php artisan test`
Expected: PASS (all tests in the suite)

- [ ] **Step 7: Run migrations against the dev database**

Run: `php artisan migrate`
Expected: output includes `Migrated: 2026_07_13_120000_create_shifts_table`, `Migrated: 2026_07_13_120100_add_shift_id_to_employees_table`, `Migrated: 2026_07_13_120200_add_shift_menu`.

- [ ] **Step 8: Manual verification**

With the local dev server running (`npm run dev` / `php artisan serve`), log in and confirm: the sidebar shows "Shift" under "Master Data" (after "Karyawan"), clicking it opens `/shifts` with an empty-state list, creating a shift (including an overnight one like 22:00–06:00) succeeds, editing/deleting it works, Import/Export buttons are present and functional, and on the Employee create/edit form a new "Shift" dropdown appears and successfully assigns a shift (visible afterward in the Employee list, detail page, and Excel/PDF export).

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_07_13_120200_add_shift_menu.php database/seeders/MenuSeeder.php tests/Feature/ShiftMenuMigrationTest.php
git commit -m "feat: add Shift sidebar menu entry under Master Data"
```
