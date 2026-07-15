# Delivery Monitor Real Data Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans, executed **one task at a time with an explicit user confirmation checkpoint after each task** (matches how the mock-data phase of this feature was executed).

**Goal:** Replace `resources/js/Pages/DeliveryMonitor/utils/mockData.ts` with real Eloquent-backed data, per `docs/superpowers/specs/2026-07-15-delivery-monitor-real-data-design.md`.

**Architecture:** Two new master tables (`delivery_slots`, `supplier_delivery_schedules`), two extended tables (`cycles` gets `delivery_date`/`delivery_slot_id`, `suppliers` gets `code`), all "live" values (`Supplier.status`, `ReceiptStatus`) computed on read by a new `DeliveryMonitorSnapshotBuilder` service class, never stored.

**Tech Stack:** Laravel 11, PHPUnit (`Tests\Feature`/`Tests\Unit`, `RefreshDatabase`), Eloquent, Inertia v2 + React/TSX.

## Global Constraints

- Follow this codebase's existing migration style: plain `Schema::table`/`Schema::create` closures, `->change()` is available without extra dbal setup (confirmed via `2026_07_14_090000_default_zero_base_price_on_products_table.php`).
- Tests use PHPUnit conventions already in `tests/Feature/CycleControllerTest.php` and `tests/Feature/SupplierControllerTest.php` (class extends `Tests\TestCase`, `use RefreshDatabase`, `test_snake_case_method` names) — do not introduce Pest.
- Inertia props must use the exact camelCase key names already defined in `resources/js/Pages/DeliveryMonitor/types.ts` (`supplierId`, `cycleNumber`, `planQty`, etc.) so frontend components need no shape changes beyond swapping their data source.
- Per user preference: **never run `git commit`**. Each task's final step stages changes with `git add` only; the user commits manually.
- Work happens directly on `main` (explicit user choice for this project).

---

### Task 1: `delivery_slots` master table

**Files:**
- Create: `database/migrations/2026_07_15_000001_create_delivery_slots_table.php`
- Create: `app/Models/DeliverySlot.php`
- Create: `database/factories/DeliverySlotFactory.php`
- Test: `tests/Feature/DeliverySlotTest.php`

**Interfaces:**
- Produces: `DeliverySlot` model with columns `id, slot_number (1-6, unique), time_start ("HH:MM:SS"), time_end, label (nullable)`, and `DeliverySlot::currentForTime(Carbon $time): ?DeliverySlot`, consumed by Task 4 and Task 7.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\DeliverySlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliverySlotTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seeds_six_fixed_windows(): void
    {
        $slots = DeliverySlot::orderBy('slot_number')->get();

        $this->assertCount(6, $slots);
        $this->assertSame(1, $slots->first()->slot_number);
        $this->assertSame('07:30:00', $slots->first()->time_start);
        $this->assertSame('09:30:00', $slots->first()->time_end);
        $this->assertSame(6, $slots->last()->slot_number);
        $this->assertSame('19:30:00', $slots->last()->time_end);
    }

    public function test_slot_number_is_unique(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DeliverySlot::create([
            'slot_number' => 1,
            'time_start' => '00:00:00',
            'time_end' => '01:00:00',
        ]);
    }

    public function test_current_for_time_matches_active_window(): void
    {
        $slot = DeliverySlot::currentForTime(\Carbon\Carbon::parse('2026-07-15 08:00:00'));

        $this->assertSame(1, $slot->slot_number);
    }

    public function test_current_for_time_clamps_before_first_window(): void
    {
        $slot = DeliverySlot::currentForTime(\Carbon\Carbon::parse('2026-07-15 05:00:00'));

        $this->assertSame(1, $slot->slot_number);
    }

    public function test_current_for_time_clamps_after_last_window(): void
    {
        $slot = DeliverySlot::currentForTime(\Carbon\Carbon::parse('2026-07-15 22:00:00'));

        $this->assertSame(6, $slot->slot_number);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DeliverySlotTest`
Expected: FAIL — `Class "App\Models\DeliverySlot" not found` (migration/model don't exist yet).

- [ ] **Step 3: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('slot_number')->unique();
            $table->time('time_start');
            $table->time('time_end');
            $table->string('label')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('delivery_slots')->insert([
            ['slot_number' => 1, 'time_start' => '07:30:00', 'time_end' => '09:30:00', 'label' => 'Pagi 1', 'created_at' => $now, 'updated_at' => $now],
            ['slot_number' => 2, 'time_start' => '09:30:00', 'time_end' => '11:30:00', 'label' => 'Pagi 2', 'created_at' => $now, 'updated_at' => $now],
            ['slot_number' => 3, 'time_start' => '11:30:00', 'time_end' => '13:30:00', 'label' => 'Siang 1', 'created_at' => $now, 'updated_at' => $now],
            ['slot_number' => 4, 'time_start' => '13:30:00', 'time_end' => '15:30:00', 'label' => 'Siang 2', 'created_at' => $now, 'updated_at' => $now],
            ['slot_number' => 5, 'time_start' => '15:30:00', 'time_end' => '17:30:00', 'label' => 'Sore 1', 'created_at' => $now, 'updated_at' => $now],
            ['slot_number' => 6, 'time_start' => '17:30:00', 'time_end' => '19:30:00', 'label' => 'Sore 2', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_slots');
    }
};
```

- [ ] **Step 4: Create the model**

```php
<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DeliverySlot extends Model
{
    use HasFactory;

    protected $fillable = ['slot_number', 'time_start', 'time_end', 'label'];

    public function scheduledSuppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_delivery_schedules')->withTimestamps();
    }

    public static function currentForTime(Carbon $time): ?self
    {
        $slots = static::orderBy('slot_number')->get();

        if ($slots->isEmpty()) {
            return null;
        }

        $nowTime = $time->format('H:i:s');

        foreach ($slots as $slot) {
            if ($nowTime >= $slot->time_start && $nowTime < $slot->time_end) {
                return $slot;
            }
        }

        return $nowTime < $slots->first()->time_start
            ? $slots->first()
            : $slots->last();
    }
}
```

- [ ] **Step 5: Create the factory**

```php
<?php

namespace Database\Factories;

use App\Models\DeliverySlot;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliverySlotFactory extends Factory
{
    protected $model = DeliverySlot::class;

    public function definition(): array
    {
        $slotNumber = $this->faker->unique()->numberBetween(1, 6);

        return [
            'slot_number' => $slotNumber,
            'time_start' => sprintf('%02d:30:00', 5 + $slotNumber * 2),
            'time_end' => sprintf('%02d:30:00', 7 + $slotNumber * 2),
            'label' => "Slot {$slotNumber}",
        ];
    }
}
```

- [ ] **Step 6: Run migration and test to verify it passes**

Run: `php artisan migrate && php artisan test --filter=DeliverySlotTest`
Expected: PASS (5 tests)

- [ ] **Step 7: Stage changes (user commits manually)**

```bash
git add database/migrations/2026_07_15_000001_create_delivery_slots_table.php app/Models/DeliverySlot.php database/factories/DeliverySlotFactory.php tests/Feature/DeliverySlotTest.php
```

---

### Task 2: `supplier_delivery_schedules` recurring template

**Files:**
- Create: `database/migrations/2026_07_15_000002_create_supplier_delivery_schedules_table.php`
- Create: `app/Models/SupplierDeliverySchedule.php`
- Modify: `app/Models/Supplier.php`
- Test: `tests/Feature/SupplierDeliveryScheduleTest.php`

**Interfaces:**
- Consumes: `DeliverySlot` (Task 1).
- Produces: `Supplier::scheduledSlots(): BelongsToMany` (to `DeliverySlot`), `Supplier::deliverySchedules(): HasMany` (to `SupplierDeliverySchedule`) — consumed by Task 6 (admin UI) and Task 7 (snapshot builder).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\DeliverySlot;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierDeliveryScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_can_be_scheduled_into_multiple_slots(): void
    {
        $supplier = Supplier::factory()->create();
        $slotOne = DeliverySlot::where('slot_number', 1)->first();
        $slotThree = DeliverySlot::where('slot_number', 3)->first();

        $supplier->scheduledSlots()->sync([$slotOne->id, $slotThree->id]);

        $this->assertEqualsCanonicalizing(
            [1, 3],
            $supplier->scheduledSlots()->pluck('slot_number')->all()
        );
    }

    public function test_same_supplier_slot_pair_cannot_repeat(): void
    {
        $supplier = Supplier::factory()->create();
        $slot = DeliverySlot::where('slot_number', 1)->first();

        \App\Models\SupplierDeliverySchedule::create([
            'supplier_id' => $supplier->id,
            'delivery_slot_id' => $slot->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \App\Models\SupplierDeliverySchedule::create([
            'supplier_id' => $supplier->id,
            'delivery_slot_id' => $slot->id,
        ]);
    }

    public function test_slot_reports_its_scheduled_suppliers(): void
    {
        $supplier = Supplier::factory()->create();
        $slot = DeliverySlot::where('slot_number', 2)->first();
        $supplier->scheduledSlots()->attach($slot->id);

        $this->assertTrue($slot->scheduledSuppliers->contains($supplier));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SupplierDeliveryScheduleTest`
Expected: FAIL — `Call to undefined method App\Models\Supplier::scheduledSlots()`

- [ ] **Step 3: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_delivery_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_slot_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['supplier_id', 'delivery_slot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_delivery_schedules');
    }
};
```

- [ ] **Step 4: Create the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierDeliverySchedule extends Model
{
    use HasFactory;

    protected $fillable = ['supplier_id', 'delivery_slot_id'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function deliverySlot(): BelongsTo
    {
        return $this->belongsTo(DeliverySlot::class);
    }
}
```

- [ ] **Step 5: Add relations to `Supplier`**

In `app/Models/Supplier.php`, add these imports:

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
```

And add these methods alongside the existing `cycles()` relation:

```php
public function deliverySchedules(): HasMany
{
    return $this->hasMany(SupplierDeliverySchedule::class);
}

public function scheduledSlots(): BelongsToMany
{
    return $this->belongsToMany(DeliverySlot::class, 'supplier_delivery_schedules')->withTimestamps();
}
```

- [ ] **Step 6: Run migration and test to verify it passes**

Run: `php artisan migrate && php artisan test --filter=SupplierDeliveryScheduleTest`
Expected: PASS (3 tests)

- [ ] **Step 7: Stage changes**

```bash
git add database/migrations/2026_07_15_000002_create_supplier_delivery_schedules_table.php app/Models/SupplierDeliverySchedule.php app/Models/Supplier.php tests/Feature/SupplierDeliveryScheduleTest.php
```

---

### Task 3: Extend `cycles` with `delivery_date` / `delivery_slot_id`

**Files:**
- Create: `database/migrations/2026_07_15_000003_add_delivery_date_and_slot_to_cycles_table.php`
- Modify: `app/Models/Cycle.php`
- Test: `tests/Feature/CycleDeliverySlotTest.php`

**Interfaces:**
- Consumes: `DeliverySlot` (Task 1).
- Produces: `Cycle.delivery_date` (Carbon date cast), `Cycle::deliverySlot(): BelongsTo` — consumed by Task 4 and Task 7.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\DeliverySlot;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CycleDeliverySlotTest extends TestCase
{
    use RefreshDatabase;

    public function test_cycle_can_be_linked_to_a_delivery_slot_and_date(): void
    {
        $supplier = Supplier::factory()->create();
        $slot = DeliverySlot::where('slot_number', 2)->first();

        $cycle = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'delivery_date' => '2026-07-15',
            'delivery_slot_id' => $slot->id,
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $cycle->delivery_date);
        $this->assertSame('2026-07-15', $cycle->delivery_date->toDateString());
        $this->assertSame(2, $cycle->deliverySlot->slot_number);
    }

    public function test_delivery_slot_and_date_are_nullable(): void
    {
        $cycle = Cycle::factory()->create();

        $this->assertNull($cycle->delivery_date);
        $this->assertNull($cycle->delivery_slot_id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CycleDeliverySlotTest`
Expected: FAIL — `Unknown column 'delivery_date'`

- [ ] **Step 3: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cycles', function (Blueprint $table) {
            $table->date('delivery_date')->nullable()->after('cycle_number');
            $table->foreignId('delivery_slot_id')->nullable()->after('delivery_date')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cycles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_slot_id');
            $table->dropColumn('delivery_date');
        });
    }
};
```

- [ ] **Step 4: Update the `Cycle` model**

In `app/Models/Cycle.php`, change `$fillable` and `casts()`, and add the relation:

```php
protected $fillable = ['supplier_id', 'cycle_number', 'status', 'received_at', 'notes', 'delivery_date', 'delivery_slot_id'];

protected function casts(): array
{
    return [
        'received_at' => 'datetime',
        'delivery_date' => 'date',
    ];
}

public function deliverySlot(): BelongsTo
{
    return $this->belongsTo(DeliverySlot::class);
}
```

- [ ] **Step 5: Run migration and test to verify it passes**

Run: `php artisan migrate && php artisan test --filter=CycleDeliverySlotTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Stage changes**

```bash
git add database/migrations/2026_07_15_000003_add_delivery_date_and_slot_to_cycles_table.php app/Models/Cycle.php tests/Feature/CycleDeliverySlotTest.php
```

---

### Task 4: Auto-set `delivery_date`/`delivery_slot_id` in `CycleController`

**Files:**
- Modify: `app/Http/Controllers/CycleController.php`
- Test: `tests/Feature/CycleControllerTest.php`

**Interfaces:**
- Consumes: `DeliverySlot::currentForTime()` (Task 1), `Cycle.delivery_date`/`delivery_slot_id` (Task 3).
- Produces: every `Cycle` created via `store()` or `quickReceiveStore()` now carries today's date + auto-detected slot — consumed by Task 7 (snapshot builder reads these to build today's schedule).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/CycleControllerTest.php` (alongside existing tests, same class):

```php
    public function test_store_sets_delivery_date_and_slot_from_current_time(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-15 08:00:00');

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($this->user)->post(route('cycles.store'), [
            'supplier_id' => $supplier->id,
            'cycle_number' => 1,
            'items' => [['product_id' => $product->id, 'quantity' => 10]],
        ]);

        $response->assertRedirect();
        $cycle = Cycle::first();
        $this->assertSame('2026-07-15', $cycle->delivery_date->toDateString());
        $this->assertSame(1, $cycle->deliverySlot->slot_number);

        \Carbon\Carbon::setTestNow();
    }

    public function test_quick_receive_store_sets_delivery_date_and_slot(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-15 18:00:00');

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $response = $this->actingAs($this->user)->post(route('cycles.quick-receive.store'), [
            'supplier_id' => $supplier->id,
            'items' => [['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5]],
        ]);

        $response->assertRedirect();
        $cycle = Cycle::first();
        $this->assertSame('2026-07-15', $cycle->delivery_date->toDateString());
        $this->assertSame(6, $cycle->deliverySlot->slot_number);

        \Carbon\Carbon::setTestNow();
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CycleControllerTest`
Expected: FAIL on the 2 new tests — `delivery_date` is null.

- [ ] **Step 3: Update `CycleController`**

Add the import at the top of `app/Http/Controllers/CycleController.php`:

```php
use App\Models\DeliverySlot;
```

In `store()`, change the `Cycle::create` call:

```php
        $slot = DeliverySlot::currentForTime(now());

        $cycle = Cycle::create([
            'supplier_id' => $validated['supplier_id'],
            'cycle_number' => $validated['cycle_number'],
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
            'delivery_date' => now()->toDateString(),
            'delivery_slot_id' => $slot?->id,
        ]);
```

In `quickReceiveStore()`, change the `Cycle::create` call inside the transaction:

```php
            $slot = DeliverySlot::currentForTime(now());

            $cycle = Cycle::create([
                'supplier_id'  => $supplierId,
                'cycle_number' => $cycleNumber,
                'status'       => 'completed',
                'received_at'  => now(),
                'delivery_date' => now()->toDateString(),
                'delivery_slot_id' => $slot?->id,
            ]);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=CycleControllerTest`
Expected: PASS (all existing + 2 new tests)

- [ ] **Step 5: Stage changes**

```bash
git add app/Http/Controllers/CycleController.php tests/Feature/CycleControllerTest.php
```

---

### Task 5: Auto-generated `Supplier.code`

**Files:**
- Create: `app/Support/SupplierCodeGenerator.php`
- Create: `database/migrations/2026_07_15_000004_add_code_to_suppliers_table.php`
- Modify: `app/Models/Supplier.php`
- Test: `tests/Unit/SupplierCodeGeneratorTest.php`
- Test: `tests/Feature/SupplierControllerTest.php`

**Interfaces:**
- Produces: `SupplierCodeGenerator::generate(string $name, array $existingCodes = []): string` (pure function), `Supplier.code` (auto-populated on create) — consumed by Task 7 (snapshot builder) and the frontend `Supplier.code` field.

- [ ] **Step 1: Write the failing unit test**

```php
<?php

namespace Tests\Unit;

use App\Support\SupplierCodeGenerator;
use PHPUnit\Framework\TestCase;

class SupplierCodeGeneratorTest extends TestCase
{
    public function test_generates_code_from_first_three_significant_words(): void
    {
        $this->assertSame('AJM', SupplierCodeGenerator::generate('PT. ADI JAYA MAKMUR'));
    }

    public function test_strips_common_legal_prefixes(): void
    {
        $this->assertSame('SM', SupplierCodeGenerator::generate('CV SEJAHTERA MANDIRI'));
    }

    public function test_appends_numeric_suffix_on_collision(): void
    {
        $this->assertSame(
            'AJM1',
            SupplierCodeGenerator::generate('PT. ADI JAYA MAKMUR', ['AJM'])
        );
    }

    public function test_increments_suffix_until_unique(): void
    {
        $this->assertSame(
            'AJM2',
            SupplierCodeGenerator::generate('PT. ADI JAYA MAKMUR', ['AJM', 'AJM1'])
        );
    }

    public function test_pads_single_word_names(): void
    {
        $this->assertSame('MX', SupplierCodeGenerator::generate('Mandiri'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SupplierCodeGeneratorTest`
Expected: FAIL — `Class "App\Support\SupplierCodeGenerator" not found`

- [ ] **Step 3: Implement the generator**

```php
<?php

namespace App\Support;

class SupplierCodeGenerator
{
    public static function generate(string $name, array $existingCodes = []): string
    {
        $words = array_values(array_filter(
            preg_split('/[\s.]+/', strtoupper(trim($name))),
            fn (string $word) => $word !== '' && ! in_array($word, ['PT', 'CV', 'UD'], true)
        ));

        if (empty($words)) {
            $words = ['X'];
        }

        $base = implode('', array_map(fn (string $word) => $word[0], array_slice($words, 0, 3)));

        if (strlen($base) < 2) {
            $base = str_pad($base, 2, 'X');
        }

        $code = $base;
        $suffix = 1;

        while (in_array($code, $existingCodes, true)) {
            $code = $base . $suffix;
            $suffix++;
        }

        return $code;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SupplierCodeGeneratorTest`
Expected: PASS (5 tests)

- [ ] **Step 5: Write the failing feature test for auto-population**

Add to `tests/Feature/SupplierControllerTest.php`:

```php
    public function test_supplier_gets_an_auto_generated_code_on_creation(): void
    {
        $supplier = \App\Models\Supplier::factory()->create(['name' => 'PT. ADI JAYA MAKMUR']);

        $this->assertSame('AJM', $supplier->code);
    }

    public function test_duplicate_supplier_name_gets_a_distinct_code(): void
    {
        \App\Models\Supplier::factory()->create(['name' => 'PT. ADI JAYA MAKMUR']);
        $second = \App\Models\Supplier::factory()->create(['name' => 'PT. ADI JAYA MAKMUR']);

        $this->assertSame('AJM1', $second->code);
    }
```

- [ ] **Step 6: Run test to verify it fails**

Run: `php artisan test --filter=SupplierControllerTest`
Expected: FAIL — `code` column doesn't exist yet.

- [ ] **Step 7: Create the migration**

```php
<?php

use App\Models\Supplier;
use App\Support\SupplierCodeGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('code', 10)->nullable()->after('name');
        });

        $usedCodes = [];
        Supplier::whereNull('code')->orderBy('id')->get()->each(function (Supplier $supplier) use (&$usedCodes) {
            $code = SupplierCodeGenerator::generate($supplier->name, $usedCodes);
            $usedCodes[] = $code;
            $supplier->code = $code;
            $supplier->saveQuietly();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('code', 10)->nullable(false)->change();
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['suppliers_code_unique']);
            $table->dropColumn('code');
        });
    }
};
```

`saveQuietly()` skips model events (so this backfill doesn't re-trigger the `creating` hook added in Step 9) and is available on all Eloquent models without extra setup.

- [ ] **Step 8: Add `code` to `Supplier::$fillable`**

```php
protected $fillable = [
    'name',
    'code',
    'contact_person',
    'email',
    'phone',
];
```

- [ ] **Step 9: Auto-generate `code` on creation**

Add to `app/Models/Supplier.php` (import `App\Support\SupplierCodeGenerator` at the top):

```php
protected static function booted(): void
{
    static::creating(function (Supplier $supplier) {
        if (empty($supplier->code)) {
            $existingCodes = static::pluck('code')->filter()->all();
            $supplier->code = SupplierCodeGenerator::generate($supplier->name, $existingCodes);
        }
    });
}
```

- [ ] **Step 10: Run migration and tests to verify they pass**

Run: `php artisan migrate && php artisan test --filter=SupplierControllerTest`
Expected: PASS (all existing + 2 new tests)

- [ ] **Step 11: Run full test suite to check for regressions**

Run: `php artisan test`
Expected: PASS — check specifically that `tests/Feature/ImportExport/SupplierImportExportTest.php` still passes (Supplier factory/import now produces a `code`, but nothing there asserts the old row shape strictly).

- [ ] **Step 12: Stage changes**

```bash
git add app/Support/SupplierCodeGenerator.php database/migrations/2026_07_15_000004_add_code_to_suppliers_table.php app/Models/Supplier.php tests/Unit/SupplierCodeGeneratorTest.php tests/Feature/SupplierControllerTest.php
```

---

### Task 6: Admin UI — assign suppliers to delivery slots

**Files:**
- Modify: `app/Http/Controllers/SupplierController.php`
- Modify: `resources/js/Pages/Master/Suppliers/Edit.tsx`
- Test: `tests/Feature/SupplierControllerTest.php`

**Interfaces:**
- Consumes: `Supplier::scheduledSlots()` (Task 2), `DeliverySlot` (Task 1).
- Produces: staff can assign a supplier to any subset of the 6 slots via the existing Supplier Edit page — this is the only place `supplier_delivery_schedules` rows get written in this phase.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/SupplierControllerTest.php`:

```php
    public function test_update_syncs_delivery_slot_schedule(): void
    {
        $supplier = \App\Models\Supplier::factory()->create();
        $supplier->addresses()->create([
            'street' => 'Jl. Test', 'city' => 'Jakarta', 'state' => 'DKI',
            'postal_code' => '12345', 'country' => 'Indonesia', 'address_type' => 'primary',
        ]);
        $slotOne = \App\Models\DeliverySlot::where('slot_number', 1)->first();
        $slotFour = \App\Models\DeliverySlot::where('slot_number', 4)->first();

        $response = $this->actingAs($this->user)->put(route('suppliers.update', $supplier), [
            'name' => $supplier->name,
            'email' => $supplier->email,
            'street' => 'Jl. Test', 'city' => 'Jakarta', 'state' => 'DKI',
            'postal_code' => '12345', 'country' => 'Indonesia',
            'delivery_slot_ids' => [$slotOne->id, $slotFour->id],
        ]);

        $response->assertRedirect();
        $this->assertEqualsCanonicalizing(
            [1, 4],
            $supplier->fresh()->scheduledSlots()->pluck('slot_number')->all()
        );
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SupplierControllerTest`
Expected: FAIL — `delivery_slot_ids` ignored, pivot table stays empty.

- [ ] **Step 3: Update `SupplierController`**

Add import: `use App\Models\DeliverySlot;`

Update `edit()`:

```php
    public function edit(Supplier $supplier)
    {
        return Inertia::render('Master/Suppliers/Edit', [
            'supplier' => $supplier->load('primaryAddress'),
            'deliverySlots' => DeliverySlot::orderBy('slot_number')->get(['id', 'slot_number', 'time_start', 'time_end', 'label']),
            'scheduledSlotIds' => $supplier->scheduledSlots()->pluck('delivery_slots.id'),
        ]);
    }
```

Update `update()` — add to the `$request->validate([...])` array:

```php
            'delivery_slot_ids' => 'nullable|array',
            'delivery_slot_ids.*' => 'exists:delivery_slots,id',
```

And after the existing `$supplier->update([...])` / address-update block, before the `return redirect()...`:

```php
        $supplier->scheduledSlots()->sync($validated['delivery_slot_ids'] ?? []);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SupplierControllerTest`
Expected: PASS

- [ ] **Step 5: Update the Edit page**

In `resources/js/Pages/Master/Suppliers/Edit.tsx`, change the component signature and form state:

```tsx
export default function Edit({ supplier, deliverySlots, scheduledSlotIds }: any) {
    const primaryAddress = supplier.primary_address || {};

    const { data, setData, put, errors } = useForm({
        name: supplier.name || '',
        contact_person: supplier.contact_person || '',
        email: supplier.email || '',
        phone: supplier.phone || '',
        street: primaryAddress.street || '',
        city: primaryAddress.city || '',
        state: primaryAddress.state || '',
        postal_code: primaryAddress.postal_code || '',
        country: primaryAddress.country || 'Indonesia',
        delivery_slot_ids: (scheduledSlotIds || []) as number[],
    });

    const toggleSlot = (slotId: number) => {
        setData('delivery_slot_ids',
            data.delivery_slot_ids.includes(slotId)
                ? data.delivery_slot_ids.filter((id) => id !== slotId)
                : [...data.delivery_slot_ids, slotId]
        );
    };
```

Add a new section inside the `<form>`, after the existing address fields and before the submit button:

```tsx
                        <div>
                            <Label>Jadwal Pengiriman (Slot Harian)</Label>
                            <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-1">
                                {(deliverySlots || []).map((slot: any) => (
                                    <label
                                        key={slot.id}
                                        className="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm cursor-pointer"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={data.delivery_slot_ids.includes(slot.id)}
                                            onChange={() => toggleSlot(slot.id)}
                                        />
                                        <span>
                                            C{slot.slot_number}
                                            <span className="block text-xs text-gray-400">
                                                {slot.time_start.slice(0, 5)}-{slot.time_end.slice(0, 5)}
                                            </span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </div>
```

- [ ] **Step 6: Verify frontend build**

Run: `npm run build`
Expected: no TypeScript/build errors.

- [ ] **Step 7: Stage changes**

```bash
git add app/Http/Controllers/SupplierController.php resources/js/Pages/Master/Suppliers/Edit.tsx tests/Feature/SupplierControllerTest.php
```

---

### Task 7: `DeliveryMonitorSnapshotBuilder` service

**Files:**
- Create: `app/Services/DeliveryMonitor/DeliveryMonitorSnapshotBuilder.php`
- Test: `tests/Feature/DeliveryMonitorSnapshotBuilderTest.php`

**Interfaces:**
- Consumes: `Supplier::scheduledSlots()` (Task 2), `Cycle.delivery_date`/`deliverySlot()` (Task 3/4), `Supplier.code` (Task 5).
- Produces: `DeliveryMonitorSnapshotBuilder::build(Carbon $date): array` returning `['suppliers' => [], 'slots' => [], 'cycles' => [], 'parts' => [], 'receipts' => []]` shaped exactly like `resources/js/Pages/DeliveryMonitor/types.ts` — consumed by Task 8.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\DeliverySlot;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\DeliveryMonitor\DeliveryMonitorSnapshotBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryMonitorSnapshotBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_supplier_with_no_schedule_is_standby(): void
    {
        Supplier::factory()->create();

        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build(Carbon::parse('2026-07-15'));

        $this->assertSame('standby', $snapshot['suppliers'][0]['status']);
    }

    public function test_supplier_with_fully_received_slot_is_done(): void
    {
        Carbon::setTestNow('2026-07-15 20:00:00');
        $supplier = Supplier::factory()->create();
        $slot = DeliverySlot::where('slot_number', 1)->first();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $supplier->scheduledSlots()->attach($slot->id);

        $cycle = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'delivery_date' => '2026-07-15',
            'delivery_slot_id' => $slot->id,
        ]);
        $cycle->items()->create(['product_id' => $product->id, 'quantity' => 10, 'received_quantity' => 10]);

        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build(Carbon::parse('2026-07-15'));

        $this->assertSame('done', $snapshot['suppliers'][0]['status']);
    }

    public function test_supplier_with_overdue_incomplete_slot_is_alert(): void
    {
        Carbon::setTestNow('2026-07-15 10:00:00');
        $supplier = Supplier::factory()->create();
        $slot = DeliverySlot::where('slot_number', 1)->first(); // ends 09:30, already passed
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $supplier->scheduledSlots()->attach($slot->id);

        $cycle = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'delivery_date' => '2026-07-15',
            'delivery_slot_id' => $slot->id,
        ]);
        $cycle->items()->create(['product_id' => $product->id, 'quantity' => 10, 'received_quantity' => 4]);

        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build(Carbon::parse('2026-07-15'));

        $this->assertSame('alert', $snapshot['suppliers'][0]['status']);
    }

    public function test_supplier_with_not_yet_overdue_incomplete_slot_is_live(): void
    {
        Carbon::setTestNow('2026-07-15 10:00:00');
        $supplier = Supplier::factory()->create();
        $slot = DeliverySlot::where('slot_number', 6)->first(); // ends 19:30, not passed yet
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $supplier->scheduledSlots()->attach($slot->id);

        $cycle = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'delivery_date' => '2026-07-15',
            'delivery_slot_id' => $slot->id,
        ]);
        $cycle->items()->create(['product_id' => $product->id, 'quantity' => 10, 'received_quantity' => 0]);

        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build(Carbon::parse('2026-07-15'));

        $this->assertSame('live', $snapshot['suppliers'][0]['status']);
    }

    public function test_receipt_status_is_computed_per_part(): void
    {
        Carbon::setTestNow('2026-07-15 10:00:00');
        $supplier = Supplier::factory()->create();
        $slot = DeliverySlot::where('slot_number', 1)->first();
        $matched = Product::factory()->create(['supplier_id' => $supplier->id]);
        $shortage = Product::factory()->create(['supplier_id' => $supplier->id]);
        $over = Product::factory()->create(['supplier_id' => $supplier->id]);
        $pending = Product::factory()->create(['supplier_id' => $supplier->id]);
        $supplier->scheduledSlots()->attach($slot->id);

        $cycle = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'delivery_date' => '2026-07-15',
            'delivery_slot_id' => $slot->id,
        ]);
        $cycle->items()->create(['product_id' => $matched->id, 'quantity' => 10, 'received_quantity' => 10]);
        $cycle->items()->create(['product_id' => $shortage->id, 'quantity' => 10, 'received_quantity' => 4]);
        $cycle->items()->create(['product_id' => $over->id, 'quantity' => 10, 'received_quantity' => 15]);
        $cycle->items()->create(['product_id' => $pending->id, 'quantity' => 10, 'received_quantity' => 0]);

        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build(Carbon::parse('2026-07-15'));
        $statusByPartId = collect($snapshot['receipts'])->pluck('status', 'partId');

        $this->assertSame('matched', $statusByPartId[$matched->id]);
        $this->assertSame('shortage', $statusByPartId[$shortage->id]);
        $this->assertSame('over', $statusByPartId[$over->id]);
        $this->assertSame('pending', $statusByPartId[$pending->id]);
    }

    public function test_snapshot_keys_match_frontend_shape(): void
    {
        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build(Carbon::parse('2026-07-15'));

        $this->assertEqualsCanonicalizing(['suppliers', 'slots', 'cycles', 'parts', 'receipts'], array_keys($snapshot));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DeliveryMonitorSnapshotBuilderTest`
Expected: FAIL — `Class "App\Services\DeliveryMonitor\DeliveryMonitorSnapshotBuilder" not found`

- [ ] **Step 3: Implement the builder**

```php
<?php

namespace App\Services\DeliveryMonitor;

use App\Models\Cycle;
use App\Models\DeliverySlot;
use App\Models\Product;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DeliveryMonitorSnapshotBuilder
{
    public function build(Carbon $date): array
    {
        $slots = DeliverySlot::orderBy('slot_number')->get();
        $suppliers = Supplier::with('scheduledSlots')->orderBy('name')->get();

        $cycles = Cycle::whereDate('delivery_date', $date)
            ->whereNotNull('delivery_slot_id')
            ->with(['items.product', 'deliverySlot'])
            ->get();

        $cyclesBySupplierSlot = $cycles->groupBy(fn (Cycle $cycle) => $cycle->supplier_id . ':' . $cycle->delivery_slot_id);

        return [
            'suppliers' => $suppliers->map(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'code' => $supplier->code,
                'name' => $supplier->name,
                'status' => $this->computeSupplierStatus($supplier, $slots, $cyclesBySupplierSlot),
            ])->values()->all(),

            'slots' => $slots->map(fn (DeliverySlot $slot) => [
                'cycleNumber' => $slot->slot_number,
                'timeStart' => substr($slot->time_start, 0, 5),
                'timeEnd' => substr($slot->time_end, 0, 5),
            ])->values()->all(),

            'cycles' => $this->buildDeliveryCycles($suppliers, $cyclesBySupplierSlot),

            'parts' => $this->buildParts($suppliers),

            'receipts' => $this->buildReceipts($cycles),
        ];
    }

    private function computeSupplierStatus(Supplier $supplier, Collection $slots, Collection $cyclesBySupplierSlot): string
    {
        $scheduledSlotIds = $supplier->scheduledSlots->pluck('id');

        if ($scheduledSlotIds->isEmpty()) {
            return 'standby';
        }

        $now = now();
        $anyIncomplete = false;
        $anyOverdue = false;

        foreach ($slots as $slot) {
            if (! $scheduledSlotIds->contains($slot->id)) {
                continue;
            }

            $slotCycles = $cyclesBySupplierSlot->get($supplier->id . ':' . $slot->id, collect());
            $plan = $slotCycles->flatMap->items->sum('quantity');
            $actual = $slotCycles->flatMap->items->sum('received_quantity');
            $isComplete = $plan > 0 && $actual >= $plan;

            if (! $isComplete) {
                $anyIncomplete = true;

                if ($now->format('H:i:s') >= $slot->time_end) {
                    $anyOverdue = true;
                }
            }
        }

        if (! $anyIncomplete) {
            return 'done';
        }

        return $anyOverdue ? 'alert' : 'live';
    }

    private function buildDeliveryCycles(Collection $suppliers, Collection $cyclesBySupplierSlot): array
    {
        $result = [];

        foreach ($suppliers as $supplier) {
            foreach ($supplier->scheduledSlots as $slot) {
                $slotCycles = $cyclesBySupplierSlot->get($supplier->id . ':' . $slot->id, collect());

                $result[] = [
                    'supplierId' => $supplier->id,
                    'cycleNumber' => $slot->slot_number,
                    'timeStart' => substr($slot->time_start, 0, 5),
                    'timeEnd' => substr($slot->time_end, 0, 5),
                    'planQty' => (int) $slotCycles->flatMap->items->sum('quantity'),
                    'actualQty' => (int) $slotCycles->flatMap->items->sum('received_quantity'),
                ];
            }
        }

        return $result;
    }

    private function buildParts(Collection $suppliers): array
    {
        $scheduledSupplierIds = $suppliers
            ->filter(fn (Supplier $supplier) => $supplier->scheduledSlots->isNotEmpty())
            ->pluck('id');

        return Product::whereIn('supplier_id', $scheduledSupplierIds)
            ->with('category')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'partNumber' => $product->part_number,
                'partName' => $product->name,
                'category' => $product->category->name ?? 'Uncategorized',
                'supplierId' => $product->supplier_id,
            ])->values()->all();
    }

    private function buildReceipts(Collection $cycles): array
    {
        $receipts = [];

        foreach ($cycles as $cycle) {
            foreach ($cycle->items as $item) {
                $receipts[] = [
                    'partId' => $item->product_id,
                    'cycleNumber' => $cycle->deliverySlot->slot_number,
                    'planQty' => (int) $item->quantity,
                    'receivedQty' => (int) $item->received_quantity,
                    'status' => $this->computeReceiptStatus((int) $item->quantity, (int) $item->received_quantity),
                ];
            }
        }

        return $receipts;
    }

    private function computeReceiptStatus(int $plan, int $received): string
    {
        if ($received === 0 && $plan > 0) {
            return 'pending';
        }

        if ($received < $plan) {
            return 'shortage';
        }

        if ($received > $plan) {
            return 'over';
        }

        return 'matched';
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=DeliveryMonitorSnapshotBuilderTest`
Expected: PASS (6 tests)

- [ ] **Step 5: Stage changes**

```bash
git add app/Services/DeliveryMonitor/DeliveryMonitorSnapshotBuilder.php tests/Feature/DeliveryMonitorSnapshotBuilderTest.php
```

---

### Task 8: Wire `DeliveryMonitorController` to real data

**Files:**
- Modify: `app/Http/Controllers/DeliveryMonitorController.php`
- Test: `tests/Feature/DeliveryMonitorControllerTest.php`

**Interfaces:**
- Consumes: `DeliveryMonitorSnapshotBuilder::build()` (Task 7).
- Produces: `GET /delivery-monitor` returns real Inertia props `suppliers, slots, cycles, parts, receipts, selectedDate` — consumed by Task 9 (frontend).

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DeliveryMonitorControllerTest`
Expected: FAIL — props (`suppliers`, `slots`, `selectedDate`) don't exist yet on the trivial controller.

- [ ] **Step 3: Update the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Services\DeliveryMonitor\DeliveryMonitorSnapshotBuilder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryMonitorController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))
            : now();

        $snapshot = (new DeliveryMonitorSnapshotBuilder())->build($date);

        return Inertia::render('DeliveryMonitor/Index', array_merge($snapshot, [
            'selectedDate' => $date->toDateString(),
        ]));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=DeliveryMonitorControllerTest`
Expected: PASS

- [ ] **Step 5: Stage changes**

```bash
git add app/Http/Controllers/DeliveryMonitorController.php tests/Feature/DeliveryMonitorControllerTest.php
```

---

### Task 9: Frontend — consume real props, retire mock data

**Files:**
- Modify: `resources/js/Pages/DeliveryMonitor/Index.tsx`
- Modify: `resources/js/Pages/DeliveryMonitor/components/PartsTable.tsx`
- Create: `resources/js/Pages/DeliveryMonitor/utils/scheduling.ts`
- Create: `resources/js/Pages/DeliveryMonitor/utils/otif.ts`
- Delete: `resources/js/Pages/DeliveryMonitor/utils/mockData.ts`

**Interfaces:**
- Consumes: Inertia props from Task 8 (`suppliers, slots, cycles, parts, receipts, selectedDate`), matching `types.ts` exactly.

- [ ] **Step 1: Create `utils/scheduling.ts`**

```ts
export interface SlotWindow {
    cycleNumber: number;
    timeStart: string;
    timeEnd: string;
}

export function getCurrentCycleNumber(now: Date, slots: SlotWindow[]): number {
    if (slots.length === 0) return 1;

    const minutesNow = now.getHours() * 60 + now.getMinutes();
    const toMinutes = (hhmm: string) => {
        const [h, m] = hhmm.split(':').map(Number);
        return h * 60 + m;
    };

    const sorted = [...slots].sort((a, b) => a.cycleNumber - b.cycleNumber);

    for (const window of sorted) {
        if (minutesNow >= toMinutes(window.timeStart) && minutesNow < toMinutes(window.timeEnd)) {
            return window.cycleNumber;
        }
    }

    if (minutesNow < toMinutes(sorted[0].timeStart)) return sorted[0].cycleNumber;
    return sorted[sorted.length - 1].cycleNumber;
}
```

- [ ] **Step 2: Create `utils/otif.ts`**

```ts
import { PartCycleReceipt } from '../types';

export function calculateOtifPercent(receipts: PartCycleReceipt[]): number {
    const totalPlan = receipts.reduce((sum, r) => sum + r.planQty, 0);
    const totalMatched = receipts.filter((r) => r.status === 'matched').reduce((sum, r) => sum + r.receivedQty, 0);
    return totalPlan > 0 ? Math.round((totalMatched / totalPlan) * 1000) / 10 : 0;
}
```

- [ ] **Step 3: Rewrite `Index.tsx`**

```tsx
import React, { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import Header from './components/Header';
import SupplierGrid from './components/SupplierGrid';
import SupplierDetailPanel from './components/SupplierDetailPanel';
import PartsTable from './components/PartsTable';
import { getCurrentCycleNumber, SlotWindow } from './utils/scheduling';
import { calculateOtifPercent } from './utils/otif';
import { useClock } from './utils/useClock';
import { Supplier, DeliveryCycle, Part, PartCycleReceipt } from './types';

const ROTATE_INTERVAL_MS = 8000;

interface IndexProps {
    suppliers: Supplier[];
    slots: SlotWindow[];
    cycles: DeliveryCycle[];
    parts: Part[];
    receipts: PartCycleReceipt[];
    selectedDate: string;
}

export default function Index({ suppliers, slots, cycles, parts, receipts: initialReceipts, selectedDate }: IndexProps) {
    const clock = useClock();
    const currentCycleNumber = getCurrentCycleNumber(clock, slots);

    // Receipts are mutable state (not the raw prop) so "Reset Receipts" in
    // PartsTable can update them and have every dependent view (OTIF badge,
    // detail panel, table) recompute live without a server round-trip.
    const [receipts, setReceipts] = useState<PartCycleReceipt[]>(initialReceipts);
    const todayOtifPercent = useMemo(() => calculateOtifPercent(receipts), [receipts]);

    const handleResetReceipts = () => {
        setReceipts((current) => current.map((r) => ({ ...r, receivedQty: 0, status: 'pending' })));
    };

    const [selectedSupplierId, setSelectedSupplierId] = useState<number | null>(suppliers[0]?.id ?? null);
    const focusedSupplier = suppliers.find((s) => s.id === selectedSupplierId);
    const [selectedDateState, setSelectedDateState] = useState<string>(selectedDate);
    const [tvMode, setTvMode] = useState(false);
    const [rotate, setRotate] = useState(false);

    useEffect(() => {
        if (!tvMode || !rotate || suppliers.length === 0) return;

        const interval = setInterval(() => {
            setSelectedSupplierId((current) => {
                const currentIndex = suppliers.findIndex((s) => s.id === current);
                const nextIndex = (currentIndex + 1) % suppliers.length;
                return suppliers[nextIndex].id;
            });
        }, ROTATE_INTERVAL_MS);

        return () => clearInterval(interval);
    }, [tvMode, rotate, suppliers]);

    const handleToggleTvMode = () => {
        setTvMode((current) => {
            const next = !current;
            if (next && document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen().catch(() => {});
            } else if (!next && document.fullscreenElement && document.exitFullscreen) {
                document.exitFullscreen().catch(() => {});
            }
            return next;
        });
    };

    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
            <Head title="Warehouse Part Delivery Monitor" />
            <Header
                suppliers={suppliers}
                selectedSupplierId={selectedSupplierId}
                onSelectSupplier={setSelectedSupplierId}
                selectedDate={selectedDateState}
                onSelectDate={setSelectedDateState}
                tvMode={tvMode}
                onToggleTvMode={handleToggleTvMode}
                rotate={rotate}
                onToggleRotate={() => setRotate((r) => !r)}
                otifPercent={todayOtifPercent}
            />
            <div className="grid grid-cols-1 lg:grid-cols-[65%_35%] gap-4 p-4">
                <SupplierGrid
                    suppliers={suppliers}
                    cycles={cycles}
                    currentCycleNumber={currentCycleNumber}
                    selectedSupplierId={selectedSupplierId}
                    onSelectSupplier={setSelectedSupplierId}
                />
                <SupplierDetailPanel
                    supplier={focusedSupplier}
                    cycles={cycles}
                    parts={parts}
                    receipts={receipts}
                    currentCycleNumber={currentCycleNumber}
                />
            </div>
            <div className="p-4">
                <PartsTable
                    parts={parts}
                    receipts={receipts}
                    suppliers={suppliers}
                    slots={slots}
                    onResetReceipts={handleResetReceipts}
                />
            </div>
        </div>
    );
}
```

> Note: `selectedDateState`/`onSelectDate` changing the date is not wired to a server refetch in this task — that would require an Inertia `router.get(route('delivery-monitor'), { date })` call, which is out of scope for this plan (spec only requires "today" to work end-to-end). Leave `onSelectDate` as local-only state, matching current behavior, and flag this as a known follow-up rather than silently implementing a partial fetch.

- [ ] **Step 4: Update `PartsTable.tsx`**

Change the import and props:

```tsx
import React, { useMemo, useRef, useState } from 'react';
import { useVirtualizer } from '@tanstack/react-virtual';
import { Part, PartCycleReceipt, Supplier } from '../types';
import { SlotWindow } from '../utils/scheduling';
import { getStatusColor } from '../utils/statusColor';

interface PartsTableProps {
    parts: Part[];
    receipts: PartCycleReceipt[];
    suppliers: Supplier[];
    slots: SlotWindow[];
    onResetReceipts: () => void;
}
```

Update the function signature:

```tsx
export default function PartsTable({ parts, receipts, suppliers, slots, onResetReceipts }: PartsTableProps) {
```

Replace both `CYCLE_WINDOWS.map(...)` usages (header row and data row) with `slots.map(...)` — the shape (`cycleNumber`, `timeStart`, `timeEnd`) is identical, so only the identifier changes:

```tsx
                        {slots.map((w) => (
```

and

```tsx
                                            {slots.map((w) => {
```

- [ ] **Step 5: Delete the mock data file**

```bash
rm resources/js/Pages/DeliveryMonitor/utils/mockData.ts
```

- [ ] **Step 6: Verify the frontend build**

Run: `npm run build`
Expected: no TypeScript errors, no remaining references to `mockData` (confirm with `grep -rn "mockData" resources/js/Pages/DeliveryMonitor/` returning nothing).

- [ ] **Step 7: Run full backend test suite**

Run: `php artisan test`
Expected: PASS — full regression check across all 9 tasks.

- [ ] **Step 8: Stage changes**

```bash
git add resources/js/Pages/DeliveryMonitor/Index.tsx resources/js/Pages/DeliveryMonitor/components/PartsTable.tsx resources/js/Pages/DeliveryMonitor/utils/scheduling.ts resources/js/Pages/DeliveryMonitor/utils/otif.ts
git rm resources/js/Pages/DeliveryMonitor/utils/mockData.ts
```

---

## Post-implementation note

`Header.tsx`'s date picker and `SupplierGrid`/`SupplierDetailPanel`/`SupplierCard` need no code changes — they already consume `suppliers`/`cycles`/`parts`/`receipts` purely through props, which is why the mock-to-real swap only touches `Index.tsx`, `PartsTable.tsx`, and the two small util files. If the user later wants live date-switching (re-fetching from the server when the date picker changes), that's a follow-up task: wire `onSelectDate` to `router.get(route('delivery-monitor'), { date }, { preserveState: true })`.
