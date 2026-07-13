# Shipment → Shopping Full Rename Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Completely rename the "Shipment" domain entity to "Shopping" — database tables/columns, PHP model/controller/exporter/factory classes, route names, RBAC permission data, tests, frontend files/routes/text, and documentation — reconciling the pre-existing half-rename (sidebar already says "Shopping" but points at `/shipments`).

**Architecture:** A new migration renames the tables/columns (existing migrations are never edited — history stays immutable). A second migration updates the *live data* in `permissions` and `menus` (both tables already contain seeded rows; renaming in place via `UPDATE` preserves `role_has_permissions` pivot links, unlike delete+recreate). PHP classes are renamed 1:1 (`Shipment`→`Shopping`, `ShipmentItem`→`ShoppingItem`, `ShipmentController`→`ShoppingController`, `ShipmentReportExporter`→`ShoppingReportExporter`). The frontend folder `Transactions/Shipments/` becomes `Transactions/Shopping/` (singular, matching the sidebar label style) while routes/tables use the standard plural `shoppings` — this is a deliberate, spec-approved asymmetry, not an inconsistency.

**Tech Stack:** Laravel 11 (PHP), MySQL 8 (native `RENAME COLUMN` preserves FK constraints), PHPUnit, Inertia.js + React (TSX), `spatie/laravel-permission`.

## Global Constraints

- Naming map (see spec §"Naming map" for the full table): tables `shipments`→`shoppings`, `shipment_items`→`shopping_items`; columns `shipment_date`→`shopping_date`, `shipment_id`→`shopping_id`; models `Shipment`→`Shopping`, `ShipmentItem`→`ShoppingItem`; controller `ShipmentController`→`ShoppingController`; routes `shipments.*`→`shoppings.*`, `reports.shipment*`→`reports.shopping*`; exporter `ShipmentReportExporter`→`ShoppingReportExporter`; permissions `*_shipments`→`*_shoppings`.
- **Exception**: the frontend folder is `Transactions/Shopping/` (singular), not `Transactions/Shoppings/` — matches the existing sidebar menu label style. This only affects the folder path and the `Inertia::render('Transactions/Shopping/...')` strings inside `ShoppingController` — nowhere else.
- The `ship()` controller action, `shoppings.ship` route name, and the `'shipped'` status enum value are **not** renamed — "ship"/"shipped" describe the physical dispatch action, not the "Shipment" entity name.
- Never edit an already-applied migration. All schema/data changes ship as new migrations.
- `permissions.name` and `menus.name`/`path` are live data — rename via `UPDATE ... WHERE`, never delete+recreate (would orphan `role_has_permissions`/`model_has_permissions` pivot rows).
- Run backend tests with `php artisan test`. Run frontend verification with `npm run build`.
- Spec reference: `docs/superpowers/specs/2026-07-13-shipment-to-shopping-rename-design.md`

---

## File Structure

### Files to Create
```
database/migrations/2026_07_13_130000_rename_shipments_to_shoppings.php
database/migrations/2026_07_13_130100_rename_shipment_permissions_and_menu_to_shopping.php
app/Models/Shopping.php                              [renamed from Shipment.php]
app/Models/ShoppingItem.php                           [renamed from ShipmentItem.php]
app/Http/Controllers/ShoppingController.php            [renamed from ShipmentController.php]
app/Services/ImportExport/Exports/ShoppingReportExporter.php  [renamed from ShipmentReportExporter.php]
database/factories/ShoppingFactory.php                 [renamed from ShipmentFactory.php]
database/factories/ShoppingItemFactory.php              [renamed from ShipmentItemFactory.php]
tests/Feature/ShoppingControllerTest.php                [renamed from ShipmentControllerTest.php]
resources/js/Pages/Transactions/Shopping/{Index,Create,Edit,Show}.tsx  [renamed from Shipments/]
resources/js/Pages/Reports/Shopping.tsx                 [renamed from Shipment.tsx]
```

### Files to Delete (superseded by the renamed files above)
```
app/Models/Shipment.php
app/Models/ShipmentItem.php
app/Http/Controllers/ShipmentController.php
app/Services/ImportExport/Exports/ShipmentReportExporter.php
database/factories/ShipmentFactory.php
database/factories/ShipmentItemFactory.php
tests/Feature/ShipmentControllerTest.php
resources/js/Pages/Transactions/Shipments/ (whole folder)
resources/js/Pages/Reports/Shipment.tsx
```

### Files to Modify
```
app/Http/Controllers/DashboardController.php
app/Http/Controllers/TvDashboardController.php
app/Http/Controllers/ReportController.php
routes/web.php
database/seeders/WmsRoleSeeder.php
database/seeders/MenuSeeder.php
tests/Feature/ReportControllerTest.php
tests/Feature/TvDashboardControllerTest.php
resources/js/Pages/Dashboard.tsx
docs/database-documentation.md
docs/database-schema.dbml
```

---

## Task 1: Database schema rename

**Files:**
- Create: `database/migrations/2026_07_13_130000_rename_shipments_to_shoppings.php`
- Test: covered by Task 5's full suite run (this task has no dedicated test file — verified via the existing `ShipmentControllerTest`/`ShoppingControllerTest` suite passing against the new schema, since those tests exercise every column/table touched here)

**Interfaces:**
- Produces: tables `shoppings` (was `shipments`), `shopping_items` (was `shipment_items`); columns `shoppings.shopping_date` (was `shipment_date`), `shopping_items.shopping_id` (was `shipment_id`, FK to `shoppings.id`, cascade delete preserved).

- [ ] **Step 1: Create the migration**

Create `database/migrations/2026_07_13_130000_rename_shipments_to_shoppings.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('shipments', 'shoppings');
        Schema::rename('shipment_items', 'shopping_items');

        Schema::table('shoppings', function (Blueprint $table) {
            $table->renameColumn('shipment_date', 'shopping_date');
        });

        Schema::table('shopping_items', function (Blueprint $table) {
            $table->renameColumn('shipment_id', 'shopping_id');
        });
    }

    public function down(): void
    {
        Schema::table('shopping_items', function (Blueprint $table) {
            $table->renameColumn('shopping_id', 'shipment_id');
        });

        Schema::table('shoppings', function (Blueprint $table) {
            $table->renameColumn('shopping_date', 'shipment_date');
        });

        Schema::rename('shopping_items', 'shipment_items');
        Schema::rename('shoppings', 'shipments');
    }
};
```

- [ ] **Step 2: Run the migration and verify the schema**

Run: `php artisan migrate`
Expected: output includes `Migrated: 2026_07_13_130000_rename_shipments_to_shoppings`.

Run: `php artisan tinker --execute="print_r(Illuminate\Support\Facades\Schema::getColumnListing('shoppings')); print_r(Illuminate\Support\Facades\Schema::getColumnListing('shopping_items'));"`
Expected: `shoppings` columns include `shopping_date` (not `shipment_date`); `shopping_items` columns include `shopping_id` (not `shipment_id`).

- [ ] **Step 3: Verify the foreign key constraint survived**

Run: `php artisan tinker --execute="echo \DB::selectOne(\"SELECT COUNT(*) as c FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME='shopping_items' AND COLUMN_NAME='shopping_id' AND REFERENCED_TABLE_NAME='shoppings'\")->c;"`
Expected: `1` (the FK constraint from `shopping_items.shopping_id` to `shoppings.id` still exists after the rename).

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_07_13_130000_rename_shipments_to_shoppings.php
git commit -m "feat: rename shipments/shipment_items tables and columns to shopping/shopping_items"
```

---

## Task 2: RBAC permissions and menu data rename

**Files:**
- Create: `database/migrations/2026_07_13_130100_rename_shipment_permissions_and_menu_to_shopping.php`
- Modify: `database/seeders/WmsRoleSeeder.php`
- Modify: `database/seeders/MenuSeeder.php`

**Interfaces:**
- Produces: `permissions` rows renamed in place (`view/create/edit/delete/ship shipments` → `...shoppings`), preserving `role_has_permissions` links.
- Produces: `menus` row (main "Shopping" item) `path` fixed from `/shipments` to `/shoppings`; `menus` row `"Shipment Report"` renamed to `"Shopping Report"` with `path` `/reports/shipment` → `/reports/shopping`.

- [ ] **Step 1: Create the data migration**

Create `database/migrations/2026_07_13_130100_rename_shipment_permissions_and_menu_to_shopping.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionRenames = [
            'view shipments' => 'view shoppings',
            'create shipments' => 'create shoppings',
            'edit shipments' => 'edit shoppings',
            'delete shipments' => 'delete shoppings',
            'ship shipments' => 'ship shoppings',
        ];

        foreach ($permissionRenames as $old => $new) {
            DB::table('permissions')->where('name', $old)->update(['name' => $new]);
        }

        DB::table('menus')->where('path', '/shipments')->update(['path' => '/shoppings']);

        DB::table('menus')->where('name', 'Shipment Report')->update([
            'name' => 'Shopping Report',
            'path' => '/reports/shopping',
        ]);
    }

    public function down(): void
    {
        $permissionRenames = [
            'view shoppings' => 'view shipments',
            'create shoppings' => 'create shipments',
            'edit shoppings' => 'edit shipments',
            'delete shoppings' => 'delete shipments',
            'ship shoppings' => 'ship shipments',
        ];

        foreach ($permissionRenames as $old => $new) {
            DB::table('permissions')->where('name', $old)->update(['name' => $new]);
        }

        DB::table('menus')->where('path', '/shoppings')->update(['path' => '/shipments']);

        DB::table('menus')->where('name', 'Shopping Report')->update([
            'name' => 'Shipment Report',
            'path' => '/reports/shipment',
        ]);
    }
};
```

- [ ] **Step 2: Run the migration and verify the data**

Run: `php artisan migrate`
Expected: output includes `Migrated: 2026_07_13_130100_rename_shipment_permissions_and_menu_to_shopping`.

Run: `php artisan tinker --execute="echo \DB::table('permissions')->where('name', 'like', '%shoppings')->count();"`
Expected: `5`

Run: `php artisan tinker --execute="echo \DB::table('menus')->where('path', '/shoppings')->count() . ' ' . \DB::table('menus')->where('name', 'Shopping Report')->count();"`
Expected: `1 1`

- [ ] **Step 3: Update WmsRoleSeeder.php for fresh installs**

In `database/seeders/WmsRoleSeeder.php`, replace all three occurrences of:

```php
            'view shipments', 'create shipments', 'edit shipments', 'delete shipments', 'ship shipments',
```

with:

```php
            'view shoppings', 'create shoppings', 'edit shoppings', 'delete shoppings', 'ship shoppings',
```

This appears at line 36 (master `$permissions` array), line 72 (`Kepala Gudang` role grant), and line 85 (`Staff Gudang` role grant — note this one is missing `'delete shipments'`/`'delete shoppings'`, keep that subset as-is, just rename the words present: `'view shoppings', 'create shoppings', 'edit shoppings', 'ship shoppings',`).

Also update the Bahasa Indonesia comment on line 61 from `// 3. Kepala Gudang — view semua + approve cycles & shipments` to `// 3. Kepala Gudang — view semua + approve cycles & shopping`.

- [ ] **Step 4: Update MenuSeeder.php for fresh installs**

In `database/seeders/MenuSeeder.php`, replace:

```php
        Menu::create([
            'name' => 'Shopping',
            'icon' => 'UploadIcon',
            'path' => '/shipments',
            'parent_id' => $transactions->id,
            'sort_order' => 3,
            'group' => 'main',
        ]);
```

with:

```php
        Menu::create([
            'name' => 'Shopping',
            'icon' => 'UploadIcon',
            'path' => '/shoppings',
            'parent_id' => $transactions->id,
            'sort_order' => 3,
            'group' => 'main',
        ]);
```

And replace:

```php
        Menu::create([
            'name' => 'Shipment Report',
            'icon' => 'ListIcon',
            'path' => '/reports/shipment',
            'parent_id' => $reportTransaction->id,
            'sort_order' => 2,
            'group' => 'main',
        ]);
```

with:

```php
        Menu::create([
            'name' => 'Shopping Report',
            'icon' => 'ListIcon',
            'path' => '/reports/shopping',
            'parent_id' => $reportTransaction->id,
            'sort_order' => 2,
            'group' => 'main',
        ]);
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_07_13_130100_rename_shipment_permissions_and_menu_to_shopping.php database/seeders/WmsRoleSeeder.php database/seeders/MenuSeeder.php
git commit -m "feat: rename Shipment RBAC permissions and menu entries to Shopping"
```

---

## Task 3: Core backend classes (Model, Controller, Exporter, Factories)

**Files:**
- Create: `app/Models/Shopping.php`, `app/Models/ShoppingItem.php`, `app/Http/Controllers/ShoppingController.php`, `app/Services/ImportExport/Exports/ShoppingReportExporter.php`, `database/factories/ShoppingFactory.php`, `database/factories/ShoppingItemFactory.php`
- Delete: `app/Models/Shipment.php`, `app/Models/ShipmentItem.php`, `app/Http/Controllers/ShipmentController.php`, `app/Services/ImportExport/Exports/ShipmentReportExporter.php`, `database/factories/ShipmentFactory.php`, `database/factories/ShipmentItemFactory.php`

**Interfaces:**
- Consumes: `shoppings`/`shopping_items` tables from Task 1.
- Produces: `App\Models\Shopping` (fillable `partner_name`, `shopping_date`, `status`, `notes`; relation `items(): HasMany` → `ShoppingItem`), `App\Models\ShoppingItem` (fillable `shopping_id`, `product_id`, `rack_id`, `quantity`; relation `shopping(): BelongsTo` → `Shopping`). Consumed by Task 4's other controllers.
- Produces: `Inertia::render('Transactions/Shopping/{Index,Create,Show,Edit}', ...)` calls with prop keys `shoppings`/`shopping` (was `shipments`/`shipment`). Consumed by Task 6's frontend pages.
- Produces: routes redirect to `route('shoppings.show'/'shoppings.index', ...)` — the actual route *definitions* are updated in Task 4.

- [ ] **Step 1: Create the Shopping model**

```bash
git mv app/Models/Shipment.php app/Models/Shopping.php
```

Replace the full contents of `app/Models/Shopping.php` with:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shopping extends Model
{
    use HasFactory;

    protected $fillable = ['partner_name', 'shopping_date', 'status', 'notes'];

    protected function casts(): array
    {
        return [
            'shopping_date' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShoppingItem::class);
    }
}
```

- [ ] **Step 2: Create the ShoppingItem model**

```bash
git mv app/Models/ShipmentItem.php app/Models/ShoppingItem.php
```

Replace the full contents of `app/Models/ShoppingItem.php` with:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingItem extends Model
{
    use HasFactory;

    protected $fillable = ['shopping_id', 'product_id', 'rack_id', 'quantity'];

    public function shopping(): BelongsTo
    {
        return $this->belongsTo(Shopping::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }
}
```

- [ ] **Step 3: Create the ShoppingController**

```bash
git mv app/Http/Controllers/ShipmentController.php app/Http/Controllers/ShoppingController.php
```

Replace the full contents of `app/Http/Controllers/ShoppingController.php` with:

```php
<?php

namespace App\Http\Controllers;

use App\Events\StockChanged;
use App\Models\Product;
use App\Models\Rack;
use App\Models\Shopping;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ShoppingController extends Controller
{
    public function index(Request $request)
    {
        $shoppings = Shopping::withCount('items')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('partner_name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Transactions/Shopping/Index', [
            'shoppings' => $shoppings,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Transactions/Shopping/Create', [
            'products'      => Product::with(['vehicleModel', 'stocks'])->where('is_active', true)->orderBy('name')->get(),
            'racks'         => Rack::orderBy('zone')->orderBy('code')->get(),
            'vehicleModels' => \App\Models\VehicleModel::orderBy('brand')->orderBy('name')->orderBy('suffix')->get(),
        ]);
    }

    private function mergeDuplicateItems(array $items): array
    {
        $merged = [];
        foreach ($items as $item) {
            $key = $item['product_id'] . '-' . $item['rack_id'];
            if (isset($merged[$key])) {
                $merged[$key]['quantity'] += $item['quantity'];
            } else {
                $merged[$key] = $item;
            }
        }
        return array_values($merged);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'partner_name' => 'required|string|max:255',
            'shopping_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.rack_id' => 'required|exists:racks,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
        $validated['items'] = $this->mergeDuplicateItems($validated['items']);

        $shopping = Shopping::create([
            'partner_name' => $validated['partner_name'],
            'shopping_date' => $validated['shopping_date'],
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
        ]);

        foreach ($validated['items'] as $item) {
            $shopping->items()->create([
                'product_id' => $item['product_id'],
                'rack_id' => $item['rack_id'],
                'quantity' => $item['quantity'],
            ]);
        }

        return redirect()->route('shoppings.show', $shopping)->with('success', 'Shopping created.');
    }

    public function show(Shopping $shopping)
    {
        return Inertia::render('Transactions/Shopping/Show', [
            'shopping' => $shopping->load('items.product.vehicleModel', 'items.rack'),
        ]);
    }

    public function edit(Shopping $shopping)
    {
        if ($shopping->status !== 'draft') {
            return back()->with('error', 'Only draft shopping records can be edited.');
        }

        return Inertia::render('Transactions/Shopping/Edit', [
            'shopping'      => $shopping->load('items.product.vehicleModel'),
            'products'      => Product::with(['vehicleModel', 'stocks'])->where('is_active', true)->orderBy('name')->get(),
            'racks'         => Rack::orderBy('zone')->orderBy('code')->get(),
            'vehicleModels' => \App\Models\VehicleModel::orderBy('brand')->orderBy('name')->orderBy('suffix')->get(),
        ]);
    }

    public function update(Request $request, Shopping $shopping)
    {
        if ($shopping->status !== 'draft') {
            return back()->with('error', 'Only draft shopping records can be edited.');
        }

        $validated = $request->validate([
            'partner_name' => 'required|string|max:255',
            'shopping_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.rack_id' => 'required|exists:racks,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
        $validated['items'] = $this->mergeDuplicateItems($validated['items']);

        $shopping->update([
            'partner_name' => $validated['partner_name'],
            'shopping_date' => $validated['shopping_date'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $shopping->items()->delete();
        foreach ($validated['items'] as $item) {
            $shopping->items()->create([
                'product_id' => $item['product_id'],
                'rack_id' => $item['rack_id'],
                'quantity' => $item['quantity'],
            ]);
        }

        return redirect()->route('shoppings.show', $shopping)->with('success', 'Shopping updated.');
    }

    public function destroy(Shopping $shopping)
    {
        if ($shopping->status !== 'draft') {
            return back()->with('error', 'Only draft shopping records can be deleted.');
        }

        $shopping->delete();

        return redirect()->route('shoppings.index')->with('success', 'Shopping deleted.');
    }

    /**
     * Ship the items — deduct from stock.
     */
    public function ship(Request $request, Shopping $shopping)
    {
        if ($shopping->status !== 'draft') {
            return back()->with('error', 'Cannot ship this shopping record.');
        }

        $result = DB::transaction(function () use ($shopping) {
            $lockedShopping = Shopping::where('id', $shopping->id)->lockForUpdate()->firstOrFail();

            if ($lockedShopping->status !== 'draft') {
                return ['ok' => false, 'error' => 'Cannot ship this shopping record.'];
            }

            $items = $lockedShopping->items()->with('product', 'rack')->get();
            $lockedStocks = [];

            foreach ($items as $item) {
                $stock = Stock::where('product_id', $item->product_id)
                    ->where('rack_id', $item->rack_id)
                    ->lockForUpdate()
                    ->first();

                if (! $stock || $stock->quantity < $item->quantity) {
                    $productName = $item->product->name ?? 'Unknown';
                    $rackCode = $item->rack->code ?? '?';

                    return [
                        'ok' => false,
                        'error' => "Insufficient stock: {$productName} in rack {$rackCode}. Available: "
                            . ($stock->quantity ?? 0)
                            . ", Requested: {$item->quantity}",
                    ];
                }

                $lockedStocks[$item->id] = $stock;
            }

            foreach ($items as $item) {
                $stock = $lockedStocks[$item->id];
                $stock->quantity -= $item->quantity;
                $stock->save();
            }

            $lockedShopping->update(['status' => 'shipped']);

            return ['ok' => true];
        });

        if (! $result['ok']) {
            return back()->with('error', $result['error']);
        }

        event(new StockChanged());

        return redirect()->route('shoppings.show', $shopping)->with('success', 'Shopping processed. Stock deducted.');
    }
}
```

- [ ] **Step 4: Create the ShoppingReportExporter**

```bash
git mv app/Services/ImportExport/Exports/ShipmentReportExporter.php app/Services/ImportExport/Exports/ShoppingReportExporter.php
```

Replace the full contents of `app/Services/ImportExport/Exports/ShoppingReportExporter.php` with:

```php
<?php

namespace App\Services\ImportExport\Exports;

use App\Models\ShoppingItem;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class ShoppingReportExporter extends BaseExporter
{
    public function __construct(private array $filters = []) {}

    public function headings(): array
    {
        return ['Tanggal', 'Partner', 'Part Number', 'Nama Produk', 'Rak', 'Qty', 'Status'];
    }

    public function exportQuery(): Builder
    {
        $filters = $this->filters;

        return ShoppingItem::query()
            ->with(['shopping', 'product', 'rack'])
            ->whereHas('shopping', function ($q) use ($filters) {
                if (! empty($filters['date_from'])) {
                    $q->whereDate('shopping_date', '>=', $filters['date_from']);
                }
                if (! empty($filters['date_to'])) {
                    $q->whereDate('shopping_date', '<=', $filters['date_to']);
                }
                if (! empty($filters['partner'])) {
                    $q->where('partner_name', 'like', '%' . $filters['partner'] . '%');
                }
                if (! empty($filters['status'])) {
                    $q->where('status', $filters['status']);
                }
            })
            ->latest('id');
    }

    public function mapRow($model): array
    {
        return [
            $model->shopping->shopping_date->format('Y-m-d'),
            $model->shopping->partner_name,
            $model->product->part_number,
            $model->product->name,
            $model->rack->code,
            $model->quantity,
            $model->shopping->status,
        ];
    }
}
```

- [ ] **Step 5: Create the factories**

```bash
git mv database/factories/ShipmentFactory.php database/factories/ShoppingFactory.php
git mv database/factories/ShipmentItemFactory.php database/factories/ShoppingItemFactory.php
```

Replace the full contents of `database/factories/ShoppingFactory.php` with:

```php
<?php

namespace Database\Factories;

use App\Models\Shopping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shopping>
 */
class ShoppingFactory extends Factory
{
    protected $model = Shopping::class;

    public function definition(): array
    {
        return [
            'partner_name' => $this->faker->company(),
            'shopping_date' => $this->faker->date(),
            'status' => 'draft',
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
```

Replace the full contents of `database/factories/ShoppingItemFactory.php` with:

```php
<?php

namespace Database\Factories;

use App\Models\Shopping;
use App\Models\ShoppingItem;
use App\Models\Product;
use App\Models\Rack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShoppingItem>
 */
class ShoppingItemFactory extends Factory
{
    protected $model = ShoppingItem::class;

    public function definition(): array
    {
        return [
            'shopping_id' => Shopping::factory(),
            'product_id' => Product::factory(),
            'rack_id' => Rack::factory(),
            'quantity' => $this->faker->numberBetween(1, 50),
        ];
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add app/Models/Shopping.php app/Models/ShoppingItem.php app/Http/Controllers/ShoppingController.php app/Services/ImportExport/Exports/ShoppingReportExporter.php database/factories/ShoppingFactory.php database/factories/ShoppingItemFactory.php
git status  # confirm the old Shipment* files show as deleted (via git mv) not orphaned
git commit -m "feat: rename Shipment model/controller/exporter/factories to Shopping"
```

---

## Task 4: Other controllers and routes

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `app/Http/Controllers/TvDashboardController.php`
- Modify: `app/Http/Controllers/ReportController.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `App\Models\Shopping`, `App\Models\ShoppingItem`, `App\Services\ImportExport\Exports\ShoppingReportExporter` from Task 3.
- Produces: routes `shoppings.index/create/store/show/edit/update/destroy/ship`, `reports.shopping`, `reports.shopping.export`. Consumed by Task 5 (tests) and Task 6 (frontend `route()` calls).
- Produces: `Dashboard` Inertia props `todayShoppings`, `metrics.pending_shoppings` (was `todayShipments`, `metrics.pending_shipments`). Consumed by Task 6's `Dashboard.tsx`.

- [ ] **Step 1: Update DashboardController**

Replace the full contents of `app/Http/Controllers/DashboardController.php` with:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Cycle;
use App\Models\Product;
use App\Models\Shopping;
use App\Models\Stock;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $totalProducts = Product::where('is_active', true)->count();

        $lowStockCount = Stock::where('quantity', '<', 5)
            ->where('quantity', '>', 0)
            ->count();

        $lowStockItems = Stock::with(['product', 'rack'])
            ->where('quantity', '<', 5)
            ->where('quantity', '>', 0)
            ->orderBy('quantity')
            ->limit(10)
            ->get()
            ->map(fn($s) => [
                'part_number' => $s->product?->part_number,
                'name' => $s->product?->name,
                'rack' => $s->rack?->code,
                'quantity' => $s->quantity,
            ]);

        $pendingCycles = Cycle::with('supplier')
            ->where('status', 'draft')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'supplier' => $c->supplier?->name,
                'cycle_number' => $c->cycle_number,
                'items_count' => $c->items()->count(),
                'created_at' => $c->created_at->format('d M Y'),
            ]);

        $todayShoppings = Shopping::withCount('items')
            ->where('status', 'draft')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'partner_name' => $s->partner_name,
                'shopping_date' => $s->shopping_date->format('d M Y'),
                'items_count' => $s->items_count,
                'status' => $s->status,
            ]);

        $totalStock = Stock::sum('quantity');

        return Inertia::render('Dashboard', [
            'metrics' => [
                'total_products' => $totalProducts,
                'total_stock' => $totalStock,
                'low_stock_count' => $lowStockCount,
                'pending_cycles' => Cycle::where('status', 'draft')->count(),
                'pending_shoppings' => Shopping::where('status', 'draft')->count(),
                'completed_cycles_today' => Cycle::where('status', 'completed')
                    ->whereDate('received_at', today())
                    ->count(),
            ],
            'lowStockItems' => $lowStockItems,
            'pendingCycles' => $pendingCycles,
            'todayShoppings' => $todayShoppings,
        ]);
    }
}
```

- [ ] **Step 2: Update TvDashboardController**

Replace the full contents of `app/Http/Controllers/TvDashboardController.php` with:

```php
<?php

namespace App\Http\Controllers;

use App\Models\CycleItem;
use App\Models\Product;
use App\Models\ShoppingItem;
use App\Models\Stock;
use Inertia\Inertia;

class TvDashboardController extends Controller
{
    public function index()
    {
        $products = Product::with('vehicleModel')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $items = $products->map(function (Product $product) {
            $totalStock = (int) Stock::where('product_id', $product->id)->sum('quantity');

            $lastReceivedItem = CycleItem::where('product_id', $product->id)
                ->where('received_quantity', '>', 0)
                ->whereHas('cycle', fn ($q) => $q->where('status', 'completed'))
                ->with('cycle')
                ->orderByDesc('updated_at')
                ->first();

            $lastShippedItem = ShoppingItem::where('product_id', $product->id)
                ->whereHas('shopping', fn ($q) => $q->where('status', 'shipped'))
                ->with('shopping')
                ->orderByDesc('updated_at')
                ->first();

            return [
                'id' => $product->id,
                'part_number' => $product->part_number,
                'name' => $product->name,
                'vehicle_model' => $product->vehicleModel ? [
                    'brand' => $product->vehicleModel->brand,
                    'name' => $product->vehicleModel->name,
                    'suffix' => $product->vehicleModel->suffix,
                ] : null,
                'total_stock' => $totalStock,
                'last_received' => $lastReceivedItem ? [
                    'quantity' => $lastReceivedItem->received_quantity,
                    'date' => optional($lastReceivedItem->cycle->received_at)->toIso8601String(),
                ] : null,
                'last_shipped' => $lastShippedItem ? [
                    'quantity' => $lastShippedItem->quantity,
                    'date' => $lastShippedItem->shopping->updated_at->toIso8601String(),
                ] : null,
            ];
        });

        $slides = $items->chunk(6)->values()->map(fn ($chunk) => $chunk->values())->all();

        return Inertia::render('TvDashboard/Index', [
            'slides' => $slides,
        ]);
    }
}
```

- [ ] **Step 3: Update ReportController**

In `app/Http/Controllers/ReportController.php`, replace the imports:

```php
use App\Models\CycleItem;
use App\Models\ShipmentItem;
use App\Models\Supplier;
use App\Services\ImportExport\DTOs\ExportConfig;
use App\Services\ImportExport\Enums\ExportFormat;
use App\Services\ImportExport\Exports\ReceivingReportExporter;
use App\Services\ImportExport\Exports\ShipmentReportExporter;
use App\Services\ImportExport\Managers\ExportManager;
```

with:

```php
use App\Models\CycleItem;
use App\Models\ShoppingItem;
use App\Models\Supplier;
use App\Services\ImportExport\DTOs\ExportConfig;
use App\Services\ImportExport\Enums\ExportFormat;
use App\Services\ImportExport\Exports\ReceivingReportExporter;
use App\Services\ImportExport\Exports\ShoppingReportExporter;
use App\Services\ImportExport\Managers\ExportManager;
```

Then replace the entire Shipment-related section (from `public function shipment(Request $request)` through the end of the class) with:

```php
    public function shopping(Request $request)
    {
        $filters = $request->only(['date_from', 'date_to', 'partner', 'status']);

        $items = $this->shoppingQuery($filters)->paginate(15)->withQueryString();

        return Inertia::render('Reports/Shopping', [
            'items' => $items,
            'summary' => $this->shoppingSummary($filters),
            'filters' => $filters,
        ]);
    }

    public function shoppingExport(Request $request)
    {
        $filters = $request->only(['date_from', 'date_to', 'partner', 'status']);
        $format = ExportFormat::from($request->query('format', 'xlsx'));

        $exporter = new ShoppingReportExporter($filters);

        $config = new ExportConfig(
            format: $format,
            fileName: 'shopping-report-' . now()->format('Y-m-d-His'),
            headings: $exporter->headings(),
            columns: [],
            exportableClass: ShoppingReportExporter::class,
        );

        return app(ExportManager::class)->download($exporter, $config);
    }

    private function shoppingQuery(array $filters): Builder
    {
        return ShoppingItem::query()
            ->with(['shopping', 'product', 'rack'])
            ->whereHas('shopping', function ($q) use ($filters) {
                if (! empty($filters['date_from'])) {
                    $q->whereDate('shopping_date', '>=', $filters['date_from']);
                }
                if (! empty($filters['date_to'])) {
                    $q->whereDate('shopping_date', '<=', $filters['date_to']);
                }
                if (! empty($filters['partner'])) {
                    $q->where('partner_name', 'like', '%' . $filters['partner'] . '%');
                }
                if (! empty($filters['status'])) {
                    $q->where('status', $filters['status']);
                }
            })
            ->latest('id');
    }

    private function shoppingSummary(array $filters): array
    {
        $items = $this->shoppingQuery($filters)->get();

        return [
            'total_transactions' => $items->pluck('shopping_id')->unique()->count(),
            'total_quantity' => (int) $items->sum('quantity'),
            'unique_products' => $items->pluck('product_id')->unique()->count(),
        ];
    }
}
```

- [ ] **Step 4: Update routes**

In `routes/web.php`, replace the import:

```php
use App\Http\Controllers\ShipmentController;
```

with:

```php
use App\Http\Controllers\ShoppingController;
```

Replace:

```php
    Route::resource('shipments', ShipmentController::class);
    Route::post('shipments/{shipment}/ship', [ShipmentController::class, 'ship'])->name('shipments.ship');
```

with:

```php
    Route::resource('shoppings', ShoppingController::class);
    Route::post('shoppings/{shopping}/ship', [ShoppingController::class, 'ship'])->name('shoppings.ship');
```

Replace:

```php
    Route::get('reports/shipment', [App\Http\Controllers\ReportController::class, 'shipment'])->name('reports.shipment');
    Route::get('reports/shipment/export', [App\Http\Controllers\ReportController::class, 'shipmentExport'])->name('reports.shipment.export');
```

with:

```php
    Route::get('reports/shopping', [App\Http\Controllers\ReportController::class, 'shopping'])->name('reports.shopping');
    Route::get('reports/shopping/export', [App\Http\Controllers\ReportController::class, 'shoppingExport'])->name('reports.shopping.export');
```

- [ ] **Step 5: Verify routes resolve**

Run: `php artisan route:list | grep shopping`
Expected: shows `shoppings.index`, `shoppings.create`, `shoppings.store`, `shoppings.show`, `shoppings.edit`, `shoppings.update`, `shoppings.destroy`, `shoppings.ship`, `reports.shopping`, `reports.shopping.export` — and `grep shipment` on the same output returns nothing.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/DashboardController.php app/Http/Controllers/TvDashboardController.php app/Http/Controllers/ReportController.php routes/web.php
git commit -m "feat: update Dashboard/TvDashboard/Report controllers and routes for Shopping rename"
```

---

## Task 5: Backend tests

**Files:**
- Create: `tests/Feature/ShoppingControllerTest.php` (renamed from `ShipmentControllerTest.php`)
- Delete: `tests/Feature/ShipmentControllerTest.php`
- Modify: `tests/Feature/ReportControllerTest.php`
- Modify: `tests/Feature/TvDashboardControllerTest.php`

**Interfaces:**
- Consumes: everything from Tasks 1–4 (schema, models, controllers, routes, RBAC/menu data).

- [ ] **Step 1: Rename and rewrite the Shipment controller test**

```bash
git mv tests/Feature/ShipmentControllerTest.php tests/Feature/ShoppingControllerTest.php
```

Replace the full contents of `tests/Feature/ShoppingControllerTest.php` with:

```php
<?php

namespace Tests\Feature;

use App\Events\StockChanged;
use App\Models\Product;
use App\Models\Rack;
use App\Models\Shopping;
use App\Models\Stock;
use App\Models\User;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ShoppingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_index_displays_shoppings(): void
    {
        Shopping::factory(3)->create();

        $response = $this->actingAs($this->user)->get(route('shoppings.index'));

        $response->assertStatus(200);
    }

    public function test_create_shows_form(): void
    {
        $response = $this->actingAs($this->user)->get(route('shoppings.create'));

        $response->assertStatus(200);
    }

    public function test_store_creates_shopping(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $data = [
            'partner_name' => 'PT Test Partner',
            'shopping_date' => '2026-06-10',
            'items' => [
                ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('shoppings.store'), $data);

        $this->assertDatabaseHas('shoppings', [
            'partner_name' => 'PT Test Partner',
            'status' => 'draft',
        ]);
        $response->assertRedirect();
    }

    public function test_create_passes_vehicle_models_to_view(): void
    {
        \App\Models\VehicleModel::factory()->create(['name' => 'Fortuner', 'brand' => 'Toyota', 'suffix' => 'VRZ']);

        $response = $this->actingAs($this->user)->get(route('shoppings.create'));

        $response->assertStatus(200);
        $response->assertSee('Fortuner');
    }

    public function test_show_displays_shopping(): void
    {
        $shopping = Shopping::factory()->create();

        $response = $this->actingAs($this->user)->get(route('shoppings.show', $shopping));

        $response->assertStatus(200);
    }

    public function test_ship_deducts_stock(): void
    {
        $rack = Rack::factory()->create();
        $product = Product::factory()->create();

        // Seed stock
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 20]);

        $shopping = Shopping::factory()->create(['status' => 'draft']);
        $shopping->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 8]);

        $response = $this->actingAs($this->user)->post(route('shoppings.ship', $shopping));

        $this->assertDatabaseHas('shoppings', ['id' => $shopping->id, 'status' => 'shipped']);
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 12]);
        $response->assertRedirect();
    }

    public function test_ship_fails_on_insufficient_stock(): void
    {
        $rack = Rack::factory()->create();
        $product = Product::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 3]);

        $shopping = Shopping::factory()->create(['status' => 'draft']);
        $shopping->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 10]);

        $response = $this->actingAs($this->user)->post(route('shoppings.ship', $shopping));

        $response->assertRedirect();
        $this->assertDatabaseHas('shoppings', ['id' => $shopping->id, 'status' => 'draft']); // unchanged
        $this->assertDatabaseHas('stocks', ['product_id' => $product->id, 'quantity' => 3]); // unchanged
    }

    public function test_destroy_deletes_draft_only(): void
    {
        $shopping = Shopping::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($this->user)->delete(route('shoppings.destroy', $shopping));

        $this->assertDatabaseMissing('shoppings', ['id' => $shopping->id]);
    }

    public function test_store_validates_required(): void
    {
        $response = $this->actingAs($this->user)->post(route('shoppings.store'), []);

        $response->assertSessionHasErrors(['partner_name', 'shopping_date', 'items']);
    }

    public function test_create_passes_products_with_stocks_to_view(): void
    {
        $vm = VehicleModel::factory()->create(['brand' => 'Toyota', 'name' => 'Rush', 'suffix' => 'TRD']);
        $rack = Rack::factory()->create();
        $product = Product::factory()->create(['vehicle_model_id' => $vm->id, 'default_rack_id' => $rack->id]);
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 7]);

        $response = $this->actingAs($this->user)->get(route('shoppings.create'));

        $response->assertStatus(200);
        $response->assertSee($product->part_number);
    }

    public function test_edit_passes_products_with_stocks_to_view(): void
    {
        $vm = VehicleModel::factory()->create(['brand' => 'Toyota', 'name' => 'Rush', 'suffix' => 'TRD']);
        $rack = Rack::factory()->create();
        $product = Product::factory()->create(['vehicle_model_id' => $vm->id, 'default_rack_id' => $rack->id]);
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5]);
        $shopping = Shopping::factory()->create(['status' => 'draft']);
        $shopping->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 2]);

        $response = $this->actingAs($this->user)->get(route('shoppings.edit', $shopping));

        $response->assertStatus(200);
    }

    public function test_store_merges_duplicate_product_and_rack_items(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $data = [
            'partner_name' => 'PT Test Partner',
            'shopping_date' => '2026-06-10',
            'items' => [
                ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5],
                ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 3],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('shoppings.store'), $data);

        $response->assertRedirect();
        $shopping = Shopping::first();
        $this->assertCount(1, $shopping->items);
        $this->assertDatabaseHas('shopping_items', [
            'shopping_id' => $shopping->id,
            'product_id' => $product->id,
            'rack_id' => $rack->id,
            'quantity' => 8,
        ]);
    }

    public function test_store_keeps_same_product_different_rack_as_separate_items(): void
    {
        $product = Product::factory()->create();
        $rackA = Rack::factory()->create();
        $rackB = Rack::factory()->create();

        $data = [
            'partner_name' => 'PT Test Partner',
            'shopping_date' => '2026-06-10',
            'items' => [
                ['product_id' => $product->id, 'rack_id' => $rackA->id, 'quantity' => 5],
                ['product_id' => $product->id, 'rack_id' => $rackB->id, 'quantity' => 3],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('shoppings.store'), $data);

        $response->assertRedirect();
        $shopping = Shopping::first();
        $this->assertCount(2, $shopping->items);
    }

    public function test_ship_second_call_is_rejected_and_stock_not_double_deducted(): void
    {
        $rack = Rack::factory()->create();
        $product = Product::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 20]);

        $shopping = Shopping::factory()->create(['status' => 'draft']);
        $shopping->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 8]);

        $first = $this->actingAs($this->user)->post(route('shoppings.ship', $shopping));
        $first->assertRedirect();

        $second = $this->actingAs($this->user)->post(route('shoppings.ship', $shopping));
        $second->assertRedirect();
        $second->assertSessionHas('error');

        $this->assertDatabaseHas('stocks', [
            'product_id' => $product->id,
            'rack_id' => $rack->id,
            'quantity' => 12,
        ]);
    }

    public function test_ship_dispatches_stock_changed_event(): void
    {
        Event::fake([StockChanged::class]);

        $rack = Rack::factory()->create();
        $product = Product::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 20]);

        $shopping = Shopping::factory()->create(['status' => 'draft']);
        $shopping->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 8]);

        $this->actingAs($this->user)->post(route('shoppings.ship', $shopping));

        Event::assertDispatched(StockChanged::class);
    }

    public function test_ship_does_not_dispatch_stock_changed_event_when_insufficient_stock(): void
    {
        Event::fake([StockChanged::class]);

        $rack = Rack::factory()->create();
        $product = Product::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 3]);

        $shopping = Shopping::factory()->create(['status' => 'draft']);
        $shopping->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 10]);

        $this->actingAs($this->user)->post(route('shoppings.ship', $shopping));

        Event::assertNotDispatched(StockChanged::class);
    }
}
```

- [ ] **Step 2: Run the Shopping controller tests**

Run: `php artisan test tests/Feature/ShoppingControllerTest.php`
Expected: PASS (all 16 tests)

- [ ] **Step 3: Update ReportControllerTest.php**

In `tests/Feature/ReportControllerTest.php`, replace the imports:

```php
use App\Models\Shipment;
use App\Models\ShipmentItem;
```

with:

```php
use App\Models\Shopping;
use App\Models\ShoppingItem;
```

Then, in the Shopping-report test block (methods `test_shipment_report_*`), apply these exact replacements throughout that block:
- `test_shipment_report_returns_200` → `test_shopping_report_returns_200`
- `test_shipment_report_filters_by_date_range` → `test_shopping_report_filters_by_date_range`
- `test_shipment_report_filters_by_partner_text_search` → `test_shopping_report_filters_by_partner_text_search`
- `test_shipment_report_filters_by_status` → `test_shopping_report_filters_by_status`
- `test_shipment_report_summary_totals` → `test_shopping_report_summary_totals`
- `test_shipment_report_export_xlsx_returns_success` → `test_shopping_report_export_xlsx_returns_success`
- `test_shipment_report_export_pdf_returns_success` → `test_shopping_report_export_pdf_returns_success`
- every `route('reports.shipment', ...)` → `route('reports.shopping', ...)`
- every `route('reports.shipment.export', ...)` → `route('reports.shopping.export', ...)`
- every `Shipment::factory()` → `Shopping::factory()`
- every `ShipmentItem::factory()` → `ShoppingItem::factory()`
- every `'shipment_id' => $...` → `'shopping_id' => $...`
- every `'shipment_date' => '...'` → `'shopping_date' => '...'`

Run this command to apply all of the above mechanically and safely (verified safe for this file — it contains no `Reports/Shipment` or `Transactions/Shipments` page-path string literals that would need different handling):

```bash
sed -i 's/Shipment/Shopping/g; s/shipment/shopping/g' tests/Feature/ReportControllerTest.php
```

- [ ] **Step 4: Verify no stray "shipment" text remains and Receiving-report tests are untouched**

Run: `grep -ic shipment tests/Feature/ReportControllerTest.php`
Expected: `0`

Run: `grep -c "function test_receiving" tests/Feature/ReportControllerTest.php`
Expected: same count as before this task (the `sed` only touched the word "shipment"/"Shipment", so Receiving-report test method names and content are untouched).

- [ ] **Step 5: Update TvDashboardControllerTest.php**

Run this command (verified safe for this file — same reasoning as Step 3):

```bash
sed -i 's/Shipment/Shopping/g; s/shipment/shopping/g' tests/Feature/TvDashboardControllerTest.php
```

Run: `grep -ic shipment tests/Feature/TvDashboardControllerTest.php`
Expected: `0`

- [ ] **Step 6: Run the full backend test suite**

Run: `php artisan test`
Expected: PASS across the whole suite (no failures introduced by this rename). Any pre-existing unrelated failures — e.g. the disabled registration/password-reset routes noted in prior work this session — are expected and not caused by this task.

- [ ] **Step 7: Commit**

```bash
git add tests/Feature/ShoppingControllerTest.php tests/Feature/ReportControllerTest.php tests/Feature/TvDashboardControllerTest.php
git commit -m "test: rename Shipment tests to Shopping and update all references"
```

---

## Task 6: Frontend files, routes, and text

**Files:**
- Create: `resources/js/Pages/Transactions/Shopping/{Index,Create,Edit,Show}.tsx` (renamed from `Shipments/`)
- Create: `resources/js/Pages/Reports/Shopping.tsx` (renamed from `Shipment.tsx`)
- Modify: `resources/js/Pages/Dashboard.tsx`

**Interfaces:**
- Consumes: routes `shoppings.*`, `reports.shopping*` from Task 4; Inertia props `shoppings`/`shopping` from Task 3's `ShoppingController`; `todayShoppings`/`pending_shoppings` from Task 4's `DashboardController`.

- [ ] **Step 1: Rename the Transactions folder and Reports file**

```bash
git mv resources/js/Pages/Transactions/Shipments resources/js/Pages/Transactions/Shopping
git mv resources/js/Pages/Reports/Shipment.tsx resources/js/Pages/Reports/Shopping.tsx
```

- [ ] **Step 2: Rewrite the 5 renamed frontend files**

These 4 files (`Transactions/Shopping/{Index,Create,Edit,Show}.tsx`, `Reports/Shopping.tsx`) contain no self-referencing page-path strings (Inertia page components never reference their own file path — only the PHP controller's `Inertia::render()` calls do, already handled in Task 3/4), so every occurrence of "shipment"/"Shipment" in these 5 files maps directly and unambiguously to "shopping"/"Shopping". Run:

```bash
sed -i 's/Shipment/Shopping/g; s/shipment/shopping/g' \
  resources/js/Pages/Transactions/Shopping/Index.tsx \
  resources/js/Pages/Transactions/Shopping/Create.tsx \
  resources/js/Pages/Transactions/Shopping/Edit.tsx \
  resources/js/Pages/Transactions/Shopping/Show.tsx \
  resources/js/Pages/Reports/Shopping.tsx
```

This renames, in each file: the exported component's destructured prop (`shipment`/`shipments` → `shopping`/`shoppings`), every `route('shipments.*'/'reports.shipment*')` call to the new route names, the `shipmentDate` state variable to `shoppingDate`, all `<Head title>`/breadcrumb/card-title/button/empty-state/confirm-dialog text ("Shipment" → "Shopping"), and the `Reports/Shopping.tsx` component's function name (`export default function Shipment(...)` → `export default function Shopping(...)`).

- [ ] **Step 3: Verify no stray "shipment" text remains in the renamed files**

Run:
```bash
grep -ic shipment resources/js/Pages/Transactions/Shopping/Index.tsx resources/js/Pages/Transactions/Shopping/Create.tsx resources/js/Pages/Transactions/Shopping/Edit.tsx resources/js/Pages/Transactions/Shopping/Show.tsx resources/js/Pages/Reports/Shopping.tsx
```
Expected: `0` for every file.

- [ ] **Step 4: Spot-check the rewritten Index.tsx**

Read `resources/js/Pages/Transactions/Shopping/Index.tsx` and confirm: `export default function Index({ shoppings, filters }: any)`, `route('shoppings.destroy', id)`, `<Head title="Shopping" />`, `<PageBreadcrumb pageTitle="Shopping" />`, `<ComponentCard title="Daftar Shopping">`, button text `Shopping Baru`, empty-state `Belum ada shopping` / `Buat shopping pengiriman barang ke mitra.` / `Buat Shopping`, table cell `s.shopping_date`, `route('shoppings.show'/'shoppings.edit', ...)`, confirm dialog `Hapus shopping ini?`.

- [ ] **Step 5: Update Dashboard.tsx**

In `resources/js/Pages/Dashboard.tsx`, replace:

```tsx
export default function Dashboard({ metrics, lowStockItems, pendingCycles, todayShipments }: any) {
```

with:

```tsx
export default function Dashboard({ metrics, lowStockItems, pendingCycles, todayShoppings }: any) {
```

Replace the "Shipments Today" section (from the `{/* Shipments Today */}` comment through its closing `</div>`):

```tsx
                {/* Shipments Today */}
                <div className="col-span-12">
                    <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                        <div className="px-6 py-5 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
                            <h3 className="text-base font-medium text-gray-800 dark:text-white/90">
                                📤 Shipment Siap Kirim
                            </h3>
                            <Link href={route('shipments.index')} className="text-sm text-brand-500 hover:text-brand-600">
                                Lihat Semua →
                            </Link>
                        </div>
                        <div className="p-6">
                            {todayShipments?.length > 0 ? (
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    {todayShipments.map((shipment: any, i: number) => (
                                        <Link
                                            key={i}
                                            href={route('shipments.show', shipment.id)}
                                            className="block p-4 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                        >
                                            <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                                                {shipment.partner_name}
                                            </p>
                                            <p className="text-xs text-gray-500 mt-1">
                                                {shipment.items_count} item · {shipment.shipment_date}
                                            </p>
                                            <span className="inline-flex mt-2 items-center px-2 py-1 text-xs font-medium rounded-full bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-400">
                                                {shipment.status}
                                            </span>
                                        </Link>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-8">
                                    <div className="text-3xl mb-2">📭</div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Tidak ada shipment pending</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
```

with:

```tsx
                {/* Shopping Today */}
                <div className="col-span-12">
                    <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                        <div className="px-6 py-5 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
                            <h3 className="text-base font-medium text-gray-800 dark:text-white/90">
                                📤 Shopping Siap Kirim
                            </h3>
                            <Link href={route('shoppings.index')} className="text-sm text-brand-500 hover:text-brand-600">
                                Lihat Semua →
                            </Link>
                        </div>
                        <div className="p-6">
                            {todayShoppings?.length > 0 ? (
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    {todayShoppings.map((shopping: any, i: number) => (
                                        <Link
                                            key={i}
                                            href={route('shoppings.show', shopping.id)}
                                            className="block p-4 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                        >
                                            <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                                                {shopping.partner_name}
                                            </p>
                                            <p className="text-xs text-gray-500 mt-1">
                                                {shopping.items_count} item · {shopping.shopping_date}
                                            </p>
                                            <span className="inline-flex mt-2 items-center px-2 py-1 text-xs font-medium rounded-full bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-400">
                                                {shopping.status}
                                            </span>
                                        </Link>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-8">
                                    <div className="text-3xl mb-2">📭</div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Tidak ada shopping pending</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
```

- [ ] **Step 6: Build to verify no type/syntax errors**

Run: `npm run build`
Expected: build succeeds, with no errors mentioning `Transactions/Shopping`, `Reports/Shopping`, or `Dashboard.tsx`, and no lingering references to the deleted `Transactions/Shipments` path.

- [ ] **Step 7: Manual verification**

With the dev server running and logged in: sidebar "Shopping" link now points at `/shoppings` and opens the renamed list page; creating/editing/shipping a Shopping record still works end-to-end (QR scanner, unit/suffix part lookup, stock deduction); the "Shopping Report" page under Report Transaction loads and exports correctly; the Dashboard's "Shopping Siap Kirim" widget populates; the TV Dashboard's "last shipped" data still populates. Also confirm a user with the "Kepala Gudang" or "Staff Gudang" role can still access Shopping pages (verifying the RBAC permission rename from Task 2 didn't lock anyone out).

- [ ] **Step 8: Commit**

```bash
git add resources/js/Pages/Transactions/Shopping resources/js/Pages/Reports/Shopping.tsx resources/js/Pages/Dashboard.tsx
git commit -m "feat: rename Shipment frontend pages, routes, and text to Shopping"
```

---

## Task 7: Documentation

**Files:**
- Modify: `docs/database-documentation.md`
- Modify: `docs/database-schema.dbml`

**Interfaces:**
- None — documentation only, no runtime dependency.

- [ ] **Step 1: Update database-documentation.md**

Run:
```bash
sed -i 's/Shipment/Shopping/g; s/shipment/shopping/g' docs/database-documentation.md
```

- [ ] **Step 2: Update database-schema.dbml**

Run:
```bash
sed -i 's/Shipment/Shopping/g; s/shipment/shopping/g' docs/database-schema.dbml
```

- [ ] **Step 3: Manually review both files for awkward prose**

Read through `docs/database-documentation.md` and `docs/database-schema.dbml` looking for sentences where the mechanical word-swap reads awkwardly in context (e.g. a sentence describing the physical shipping/dispatch process might read oddly with "shopping" substituted for the action rather than the entity name) and lightly copy-edit those specific sentences for clarity. The table/column/index names themselves (`shoppings`, `shopping_items`, `idx_shoppings_status`, `idx_si_shopping_id`, etc.) should NOT be changed further — they already match Task 1's actual schema.

- [ ] **Step 4: Verify no stray references**

Run: `grep -ic shipment docs/database-documentation.md docs/database-schema.dbml`
Expected: `0` for both files.

- [ ] **Step 5: Commit**

```bash
git add docs/database-documentation.md docs/database-schema.dbml
git commit -m "docs: update database documentation for Shipment to Shopping rename"
```
