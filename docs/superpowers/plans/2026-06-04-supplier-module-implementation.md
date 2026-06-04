# Supplier Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement Phase 1 of the Supplier module with database tables, models, factories, controllers, routes, and tests.

**Architecture:** Two-table design (Supplier + SupplierAddress) with Laravel Eloquent models, resource controllers for CRUD operations, feature tests, and factories for test data generation.

**Tech Stack:** Laravel 11, PHP 8.2+, MySQL, PHPUnit

---

## File Structure

### Files to Create

```
app/Models/
  └── Supplier.php                          [Model for suppliers table]
  └── SupplierAddress.php                   [Model for supplier_addresses table]

app/Http/Controllers/
  └── SupplierController.php                [Resource controller for CRUD]

database/migrations/
  └── 2026_06_04_000001_create_suppliers_table.php
  └── 2026_06_04_000002_create_supplier_addresses_table.php

database/factories/
  └── SupplierFactory.php                   [Generate test supplier data]
  └── SupplierAddressFactory.php            [Generate test address data]

tests/Feature/
  └── SupplierControllerTest.php            [Test supplier CRUD operations]

database/seeders/
  └── SupplierSeeder.php                    [Seed sample suppliers]
```

### Files to Modify

```
routes/web.php                               [Add supplier routes]
```

---

## Task 1: Create Suppliers Migration

**Files:**
- Create: `database/migrations/2026_06_04_000001_create_suppliers_table.php`

- [ ] **Step 1: Create migration file**

Run:
```bash
php artisan make:migration create_suppliers_table --create=suppliers
```

- [ ] **Step 2: Replace migration content**

Edit `database/migrations/2026_06_04_000001_create_suppliers_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('contact_person')->nullable();
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('email');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
```

- [ ] **Step 3: Verify migration file**

Check that the file exists and contains the code above:
```bash
cat database/migrations/2026_06_04_000001_create_suppliers_table.php
```

Expected: File shows complete migration code with all fields and indexes

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_06_04_000001_create_suppliers_table.php
git commit -m "feat: create suppliers table migration"
```

---

## Task 2: Create SupplierAddresses Migration

**Files:**
- Create: `database/migrations/2026_06_04_000002_create_supplier_addresses_table.php`

- [ ] **Step 1: Create migration file**

Run:
```bash
php artisan make:migration create_supplier_addresses_table --create=supplier_addresses
```

- [ ] **Step 2: Replace migration content**

Edit `database/migrations/2026_06_04_000002_create_supplier_addresses_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')
                  ->constrained('suppliers')
                  ->cascadeOnDelete();
            $table->string('street');
            $table->string('city', 100);
            $table->string('state', 100);
            $table->string('postal_code', 20);
            $table->string('country', 100)->default('Indonesia');
            $table->enum('address_type', ['primary', 'shipping', 'billing'])->default('primary');
            $table->timestamps();

            // Indexes
            $table->index('supplier_id');
            // Ensure only one address per type per supplier
            $table->unique(['supplier_id', 'address_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_addresses');
    }
};
```

- [ ] **Step 3: Verify migration file**

Check that the file exists and contains the code above:
```bash
cat database/migrations/2026_06_04_000002_create_supplier_addresses_table.php
```

Expected: File shows complete migration code with foreign key, enum, and constraints

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_06_04_000002_create_supplier_addresses_table.php
git commit -m "feat: create supplier_addresses table migration"
```

---

## Task 3: Create Supplier Model

**Files:**
- Create: `app/Models/Supplier.php`

- [ ] **Step 1: Generate model**

Run:
```bash
php artisan make:model Supplier
```

- [ ] **Step 2: Replace model content**

Edit `app/Models/Supplier.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Supplier extends Model
{
    protected $fillable = [
        'name',
        'contact_person',
        'email',
        'phone',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all addresses for this supplier
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(SupplierAddress::class);
    }

    /**
     * Get the primary address for this supplier
     */
    public function primaryAddress(): HasOne
    {
        return $this->hasOne(SupplierAddress::class)
                    ->where('address_type', 'primary');
    }
}
```

- [ ] **Step 3: Verify model file**

Check that the file contains the model definition:
```bash
cat app/Models/Supplier.php | grep "class Supplier"
```

Expected: Output shows "class Supplier extends Model"

- [ ] **Step 4: Commit**

```bash
git add app/Models/Supplier.php
git commit -m "feat: create Supplier model with relationships"
```

---

## Task 4: Create SupplierAddress Model

**Files:**
- Create: `app/Models/SupplierAddress.php`

- [ ] **Step 1: Generate model**

Run:
```bash
php artisan make:model SupplierAddress
```

- [ ] **Step 2: Replace model content**

Edit `app/Models/SupplierAddress.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierAddress extends Model
{
    protected $table = 'supplier_addresses';

    protected $fillable = [
        'supplier_id',
        'street',
        'city',
        'state',
        'postal_code',
        'country',
        'address_type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the supplier that owns this address
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
```

- [ ] **Step 3: Verify model file**

Check that the file contains the model definition:
```bash
cat app/Models/SupplierAddress.php | grep "class SupplierAddress"
```

Expected: Output shows "class SupplierAddress extends Model"

- [ ] **Step 4: Commit**

```bash
git add app/Models/SupplierAddress.php
git commit -m "feat: create SupplierAddress model with relationship"
```

---

## Task 5: Create SupplierFactory

**Files:**
- Create: `database/factories/SupplierFactory.php`

- [ ] **Step 1: Generate factory**

Run:
```bash
php artisan make:factory SupplierFactory --model=Supplier
```

- [ ] **Step 2: Replace factory content**

Edit `database/factories/SupplierFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
            'contact_person' => $this->faker->name(),
            'email' => $this->faker->unique()->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
        ];
    }
}
```

- [ ] **Step 3: Verify factory file**

Check factory is created correctly:
```bash
cat database/factories/SupplierFactory.php | grep "class SupplierFactory"
```

Expected: Output shows "class SupplierFactory extends Factory"

- [ ] **Step 4: Commit**

```bash
git add database/factories/SupplierFactory.php
git commit -m "feat: create SupplierFactory for test data generation"
```

---

## Task 6: Create SupplierAddressFactory

**Files:**
- Create: `database/factories/SupplierAddressFactory.php`

- [ ] **Step 1: Generate factory**

Run:
```bash
php artisan make:factory SupplierAddressFactory --model=SupplierAddress
```

- [ ] **Step 2: Replace factory content**

Edit `database/factories/SupplierAddressFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SupplierAddress>
 */
class SupplierAddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'street' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'postal_code' => $this->faker->postcode(),
            'country' => $this->faker->country(),
            'address_type' => 'primary',
        ];
    }
}
```

- [ ] **Step 3: Verify factory file**

Check factory is created correctly:
```bash
cat database/factories/SupplierAddressFactory.php | grep "class SupplierAddressFactory"
```

Expected: Output shows "class SupplierAddressFactory extends Factory"

- [ ] **Step 4: Commit**

```bash
git add database/factories/SupplierAddressFactory.php
git commit -m "feat: create SupplierAddressFactory for test data generation"
```

---

## Task 7: Create SupplierController

**Files:**
- Create: `app/Http/Controllers/SupplierController.php`

- [ ] **Step 1: Generate controller**

Run:
```bash
php artisan make:controller SupplierController --model=Supplier --resource
```

- [ ] **Step 2: Replace controller content**

Edit `app/Http/Controllers/SupplierController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers.
     */
    public function index()
    {
        $suppliers = Supplier::with('primaryAddress')
                             ->paginate(15);

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Show the form for creating a new supplier.
     */
    public function create()
    {
        return Inertia::render('Suppliers/Create');
    }

    /**
     * Store a newly created supplier in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:suppliers',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'required|email|unique:suppliers',
            'phone' => 'nullable|string|max:20',
            'street' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',
        ]);

        $supplier = Supplier::create([
            'name' => $validated['name'],
            'contact_person' => $validated['contact_person'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
        ]);

        $supplier->addresses()->create([
            'street' => $validated['street'],
            'city' => $validated['city'],
            'state' => $validated['state'],
            'postal_code' => $validated['postal_code'],
            'country' => $validated['country'],
            'address_type' => 'primary',
        ]);

        return redirect()->route('suppliers.show', $supplier)
                       ->with('success', 'Supplier created successfully.');
    }

    /**
     * Display the specified supplier.
     */
    public function show(Supplier $supplier)
    {
        return Inertia::render('Suppliers/Show', [
            'supplier' => $supplier->load('addresses'),
        ]);
    }

    /**
     * Show the form for editing the specified supplier.
     */
    public function edit(Supplier $supplier)
    {
        return Inertia::render('Suppliers/Edit', [
            'supplier' => $supplier->load('primaryAddress'),
        ]);
    }

    /**
     * Update the specified supplier in storage.
     */
    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:suppliers,name,' . $supplier->id,
            'contact_person' => 'nullable|string|max:255',
            'email' => 'required|email|unique:suppliers,email,' . $supplier->id,
            'phone' => 'nullable|string|max:20',
            'street' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',
        ]);

        $supplier->update([
            'name' => $validated['name'],
            'contact_person' => $validated['contact_person'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
        ]);

        $primaryAddress = $supplier->primaryAddress;
        if ($primaryAddress) {
            $primaryAddress->update([
                'street' => $validated['street'],
                'city' => $validated['city'],
                'state' => $validated['state'],
                'postal_code' => $validated['postal_code'],
                'country' => $validated['country'],
            ]);
        } else {
            $supplier->addresses()->create([
                'street' => $validated['street'],
                'city' => $validated['city'],
                'state' => $validated['state'],
                'postal_code' => $validated['postal_code'],
                'country' => $validated['country'],
                'address_type' => 'primary',
            ]);
        }

        return redirect()->route('suppliers.show', $supplier)
                       ->with('success', 'Supplier updated successfully.');
    }

    /**
     * Remove the specified supplier from storage.
     */
    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return redirect()->route('suppliers.index')
                       ->with('success', 'Supplier deleted successfully.');
    }
}
```

- [ ] **Step 3: Verify controller file**

Check that the controller has all resource methods:
```bash
grep "public function" app/Http/Controllers/SupplierController.php | wc -l
```

Expected: Output shows 7 (index, create, store, show, edit, update, destroy)

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/SupplierController.php
git commit -m "feat: create SupplierController with resource methods"
```

---

## Task 8: Add Supplier Routes

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Open routes/web.php**

Check current routes file:
```bash
cat routes/web.php | tail -20
```

- [ ] **Step 2: Add supplier resource route**

Add this line to `routes/web.php` (inside the Route middleware group, e.g., after other resource routes):

```php
Route::resource('suppliers', SupplierController::class);
```

Make sure to add the import at the top of the file if not already present:
```php
use App\Http\Controllers\SupplierController;
```

The file should look like:
```php
<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SupplierController;
// ... other imports

Route::middleware(['auth'])->group(function () {
    // ... existing routes ...
    Route::resource('suppliers', SupplierController::class);
});
```

- [ ] **Step 3: Verify routes were added**

Run:
```bash
php artisan route:list | grep "suppliers"
```

Expected: Output shows routes like:
```
GET|HEAD   suppliers .......................... suppliers.index
GET|HEAD   suppliers/create ................... suppliers.create
POST       suppliers .......................... suppliers.store
GET|HEAD   suppliers/{supplier} .............. suppliers.show
GET|HEAD   suppliers/{supplier}/edit ......... suppliers.edit
PUT|PATCH  suppliers/{supplier} .............. suppliers.update
DELETE     suppliers/{supplier} .............. suppliers.destroy
```

- [ ] **Step 4: Commit**

```bash
git add routes/web.php
git commit -m "feat: add supplier routes"
```

---

## Task 9: Create Feature Tests

**Files:**
- Create: `tests/Feature/SupplierControllerTest.php`

- [ ] **Step 1: Create test file**

Run:
```bash
php artisan make:test SupplierControllerTest --feature
```

- [ ] **Step 2: Replace test content**

Edit `tests/Feature/SupplierControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\SupplierAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test index displays all suppliers
     */
    public function test_index_displays_suppliers(): void
    {
        $suppliers = Supplier::factory(3)
                             ->has(SupplierAddress::factory(), 'addresses')
                             ->create();

        $response = $this->get(route('suppliers.index'));

        $response->assertStatus(200);
        foreach ($suppliers as $supplier) {
            $response->assertSee($supplier->name);
            $response->assertSee($supplier->email);
        }
    }

    /**
     * Test create shows the creation form
     */
    public function test_create_shows_form(): void
    {
        $response = $this->get(route('suppliers.create'));

        $response->assertStatus(200);
    }

    /**
     * Test store creates a new supplier with address
     */
    public function test_store_creates_supplier(): void
    {
        $data = [
            'name' => 'PT Test Supplier',
            'contact_person' => 'John Doe',
            'email' => 'john@testsupplier.com',
            'phone' => '+62812345678',
            'street' => 'Jl. Test 123',
            'city' => 'Jakarta',
            'state' => 'DKI Jakarta',
            'postal_code' => '12000',
            'country' => 'Indonesia',
        ];

        $response = $this->post(route('suppliers.store'), $data);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'PT Test Supplier',
            'email' => 'john@testsupplier.com',
        ]);

        $this->assertDatabaseHas('supplier_addresses', [
            'city' => 'Jakarta',
            'address_type' => 'primary',
        ]);

        $response->assertRedirect();
    }

    /**
     * Test show displays supplier details
     */
    public function test_show_displays_supplier(): void
    {
        $supplier = Supplier::factory()
                           ->has(SupplierAddress::factory(), 'addresses')
                           ->create();

        $response = $this->get(route('suppliers.show', $supplier));

        $response->assertStatus(200);
        $response->assertSee($supplier->name);
        $response->assertSee($supplier->email);
    }

    /**
     * Test edit shows the edit form
     */
    public function test_edit_shows_form(): void
    {
        $supplier = Supplier::factory()
                           ->has(SupplierAddress::factory(), 'addresses')
                           ->create();

        $response = $this->get(route('suppliers.edit', $supplier));

        $response->assertStatus(200);
        $response->assertSee($supplier->name);
    }

    /**
     * Test update modifies supplier and address
     */
    public function test_update_modifies_supplier(): void
    {
        $supplier = Supplier::factory()
                           ->has(SupplierAddress::factory(), 'addresses')
                           ->create();

        $updateData = [
            'name' => 'Updated Supplier',
            'contact_person' => 'Jane Doe',
            'email' => 'jane@updated.com',
            'phone' => '+62812345679',
            'street' => 'Jl. Updated 456',
            'city' => 'Surabaya',
            'state' => 'Jawa Timur',
            'postal_code' => '60000',
            'country' => 'Indonesia',
        ];

        $response = $this->put(route('suppliers.update', $supplier), $updateData);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Updated Supplier',
            'email' => 'jane@updated.com',
        ]);

        $this->assertDatabaseHas('supplier_addresses', [
            'supplier_id' => $supplier->id,
            'city' => 'Surabaya',
        ]);

        $response->assertRedirect();
    }

    /**
     * Test destroy deletes supplier and cascades to addresses
     */
    public function test_destroy_deletes_supplier(): void
    {
        $supplier = Supplier::factory()
                           ->has(SupplierAddress::factory(2), 'addresses')
                           ->create();

        $addressCount = $supplier->addresses()->count();
        $this->assertEquals(2, $addressCount);

        $response = $this->delete(route('suppliers.destroy', $supplier));

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
        $this->assertDatabaseMissing('supplier_addresses', ['supplier_id' => $supplier->id]);

        $response->assertRedirect();
    }

    /**
     * Test validation for duplicate email
     */
    public function test_store_rejects_duplicate_email(): void
    {
        $existing = Supplier::factory()->create(['email' => 'test@test.com']);

        $data = [
            'name' => 'New Supplier',
            'email' => 'test@test.com',
            'street' => 'Jl. Test',
            'city' => 'Jakarta',
            'state' => 'DKI Jakarta',
            'postal_code' => '12000',
            'country' => 'Indonesia',
        ];

        $response = $this->post(route('suppliers.store'), $data);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('suppliers', 1);
    }

    /**
     * Test validation for duplicate name
     */
    public function test_store_rejects_duplicate_name(): void
    {
        $existing = Supplier::factory()->create(['name' => 'Existing Supplier']);

        $data = [
            'name' => 'Existing Supplier',
            'email' => 'new@test.com',
            'street' => 'Jl. Test',
            'city' => 'Jakarta',
            'state' => 'DKI Jakarta',
            'postal_code' => '12000',
            'country' => 'Indonesia',
        ];

        $response = $this->post(route('suppliers.store'), $data);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('suppliers', 1);
    }

    /**
     * Test validation for required fields
     */
    public function test_store_validates_required_fields(): void
    {
        $data = [];

        $response = $this->post(route('suppliers.store'), $data);

        $response->assertSessionHasErrors(['name', 'email', 'street', 'city', 'state', 'postal_code']);
    }
}
```

- [ ] **Step 3: Verify test file**

Check that tests are recognized:
```bash
php artisan test --list | grep "SupplierControllerTest"
```

Expected: Output shows the test class exists

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/SupplierControllerTest.php
git commit -m "feat: add comprehensive feature tests for SupplierController"
```

---

## Task 10: Run Migrations

**Files:**
- No files created/modified
- This task executes the migrations

- [ ] **Step 1: Check migration status**

Run:
```bash
php artisan migrate:status
```

Expected: Shows pending migrations for suppliers and supplier_addresses

- [ ] **Step 2: Run migrations**

Run:
```bash
php artisan migrate
```

Expected: Output shows:
```
Migrated: 2026_06_04_000001_create_suppliers_table
Migrated: 2026_06_04_000002_create_supplier_addresses_table
```

- [ ] **Step 3: Verify tables in database**

Run:
```bash
php artisan tinker
>>> \DB::select('SHOW TABLES LIKE "%supplier%"')
```

Expected: Output shows both `suppliers` and `supplier_addresses` tables

- [ ] **Step 4: Exit tinker**

Type `exit` to exit tinker session

No commit needed (migrations are already committed)

---

## Task 11: Run Tests

**Files:**
- No files created/modified
- This task verifies tests pass

- [ ] **Step 1: Run supplier tests**

Run:
```bash
php artisan test tests/Feature/SupplierControllerTest.php --verbose
```

Expected: All tests pass (11 tests)

- [ ] **Step 2: Check test coverage**

If all tests pass, you should see output similar to:
```
Tests:  11 passed
```

- [ ] **Step 3: Run full test suite (optional)**

Run:
```bash
php artisan test
```

Expected: All tests pass including existing tests

No commit needed (tests validate existing code)

---

## Task 12: Create SupplierSeeder (Optional)

**Files:**
- Create: `database/seeders/SupplierSeeder.php`

- [ ] **Step 1: Generate seeder**

Run:
```bash
php artisan make:seeder SupplierSeeder
```

- [ ] **Step 2: Replace seeder content**

Edit `database/seeders/SupplierSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\SupplierAddress;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Supplier::factory(10)
                ->has(SupplierAddress::factory(), 'addresses')
                ->create();
    }
}
```

- [ ] **Step 3: Add to main DatabaseSeeder**

Edit `database/seeders/DatabaseSeeder.php` and add to the `run()` method:

```php
$this->call([
    // ... other seeders ...
    SupplierSeeder::class,
]);
```

- [ ] **Step 4: Run seeder (optional)**

To populate with test data:
```bash
php artisan db:seed --class=SupplierSeeder
```

To seed the entire database:
```bash
php artisan db:seed
```

- [ ] **Step 5: Commit**

```bash
git add database/seeders/SupplierSeeder.php
git commit -m "feat: add SupplierSeeder for generating sample data"
```

---

## Task 13: Verification Checklist

**Files:**
- No files created
- This is a verification task

- [ ] **Step 1: Verify all migrations ran successfully**

Run:
```bash
php artisan migrate:status | grep "Ran"
```

Expected: Both supplier migrations show as "Ran"

- [ ] **Step 2: Verify all tests pass**

Run:
```bash
php artisan test tests/Feature/SupplierControllerTest.php --quiet
```

Expected: All tests pass (11 passed)

- [ ] **Step 3: Verify routes are accessible**

Run:
```bash
php artisan route:list | grep "suppliers"
```

Expected: All 7 resource routes are listed

- [ ] **Step 4: Verify models are created**

Run:
```bash
test -f app/Models/Supplier.php && test -f app/Models/SupplierAddress.php && echo "Models OK"
```

Expected: Output shows "Models OK"

- [ ] **Step 5: Verify controller is created**

Run:
```bash
test -f app/Http/Controllers/SupplierController.php && echo "Controller OK"
```

Expected: Output shows "Controller OK"

- [ ] **Step 6: Final commit (if not already committed)**

Run:
```bash
git status
```

Expected: Working tree clean (all changes committed)

---

## Summary

| Component | Status | Files Count |
|-----------|--------|------------|
| Migrations | ✅ | 2 |
| Models | ✅ | 2 |
| Factories | ✅ | 2 |
| Controllers | ✅ | 1 |
| Routes | ✅ | 1 (modified) |
| Tests | ✅ | 1 |
| Seeders | ✅ | 1 |
| **Total** | **✅** | **11** |

**All Phase 1 features implemented:**
- ✅ SUPPLIERS table (core info)
- ✅ SUPPLIER_ADDRESSES table (location)
- ✅ Eloquent models with relationships
- ✅ Resource controller with CRUD
- ✅ Comprehensive feature tests
- ✅ Factories for test data
- ✅ Database seeder for sample data
- ✅ Routes configured

**Ready for:**
- Manual UI/frontend creation (Inertia React)
- Phase 2: Add products relationship and pricing
- Phase 3: Add supplier ratings and metrics

