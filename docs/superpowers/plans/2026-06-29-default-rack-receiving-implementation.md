# Default Rack on Product & Smart Pre-fill in Receiving — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `default_rack_id` to products and `rack_id` to cycle_items so the Receiving form auto-fills the rack from an explicit default or from history (last received rack for that product).

**Architecture:** Two new nullable FK columns (`products.default_rack_id` and `cycle_items.rack_id`) plus a batched history query in `CycleController::show()`. The frontend uses a priority cascade (explicit default → history → empty) and shows a read-only badge indicating the pre-fill source. User can always override.

**Tech Stack:** Laravel 11, Inertia.js, React/TSX, MySQL, PHPUnit (RefreshDatabase), Eloquent.

---

## File Map

| File | Change |
|------|--------|
| `database/migrations/2026_06_29_000001_add_default_rack_id_to_products_table.php` | New — nullable FK `default_rack_id` on products |
| `database/migrations/2026_06_29_000002_add_rack_id_to_cycle_items_table.php` | New — nullable FK `rack_id` on cycle_items |
| `app/Models/Product.php` | Add `default_rack_id` to `$fillable`, add `defaultRack()` relation |
| `app/Models/CycleItem.php` | Add `rack_id` to `$fillable`, add `rack()` relation |
| `database/factories/ProductFactory.php` | Add optional `default_rack_id` field |
| `app/Http/Controllers/ProductController.php` | Add validation + pass racks to create/edit |
| `resources/js/Pages/Master/Products/Create.tsx` | Add "Rack Default" SearchableSelect |
| `resources/js/Pages/Master/Products/Edit.tsx` | Add "Rack Default" SearchableSelect with existing value |
| `resources/js/Pages/Master/Products/Show.tsx` | Display default rack name |
| `app/Http/Controllers/CycleController.php` | Update `show()` + `receive()` |
| `resources/js/Pages/Transactions/Cycles/Show.tsx` | Pre-fill rack + badge |
| `tests/Feature/ProductControllerTest.php` | Add default_rack tests |
| `tests/Feature/CycleControllerTest.php` | Add receive-saves-rack_id + show-with-history tests |

---

## Task 1: Migrations

**Files:**
- Create: `database/migrations/2026_06_29_000001_add_default_rack_id_to_products_table.php`
- Create: `database/migrations/2026_06_29_000002_add_rack_id_to_cycle_items_table.php`

- [ ] **Step 1: Create migration for products.default_rack_id**

```php
<?php
// database/migrations/2026_06_29_000001_add_default_rack_id_to_products_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('default_rack_id')
                ->nullable()
                ->after('is_active')
                ->constrained('racks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['default_rack_id']);
            $table->dropColumn('default_rack_id');
        });
    }
};
```

- [ ] **Step 2: Create migration for cycle_items.rack_id**

```php
<?php
// database/migrations/2026_06_29_000002_add_rack_id_to_cycle_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cycle_items', function (Blueprint $table) {
            $table->foreignId('rack_id')
                ->nullable()
                ->after('received_quantity')
                ->constrained('racks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cycle_items', function (Blueprint $table) {
            $table->dropForeign(['rack_id']);
            $table->dropColumn('rack_id');
        });
    }
};
```

- [ ] **Step 3: Run migrations (requires Docker containers to be running)**

```bash
php artisan migrate
```

Expected: two new migrations applied, no errors.

---

## Task 2: Model Updates

**Files:**
- Modify: `app/Models/Product.php`
- Modify: `app/Models/CycleItem.php`
- Modify: `database/factories/ProductFactory.php`

- [ ] **Step 1: Update Product model**

Replace the `$fillable` array and add relation in `app/Models/Product.php`:

```php
protected $fillable = [
    'part_number',
    'name',
    'vehicle_model_id',
    'supplier_id',
    'category_id',
    'unit',
    'description',
    'base_price',
    'is_active',
    'default_rack_id',
];
```

Add this method after the existing `category()` relation:

```php
public function defaultRack(): BelongsTo
{
    return $this->belongsTo(Rack::class, 'default_rack_id');
}
```

- [ ] **Step 2: Update CycleItem model**

Replace `$fillable` in `app/Models/CycleItem.php`:

```php
protected $fillable = ['cycle_id', 'product_id', 'quantity', 'received_quantity', 'rack_id', 'notes'];
```

Add this relation after the existing `product()` method (no import needed — same namespace):

```php
public function rack(): BelongsTo
{
    return $this->belongsTo(Rack::class);
}
```

- [ ] **Step 3: Update ProductFactory**

Add `default_rack_id` as optional to `database/factories/ProductFactory.php` definition array:

```php
'default_rack_id' => null,
```

The full `definition()` becomes:

```php
public function definition(): array
{
    return [
        'part_number' => strtoupper($this->faker->unique()->bothify('P####-??###')),
        'name' => $this->faker->words(3, true),
        'vehicle_model_id' => fn () => VehicleModel::inRandomOrder()->value('id') ?? VehicleModel::factory(),
        'supplier_id' => fn () => Supplier::inRandomOrder()->value('id') ?? Supplier::factory(),
        'category_id' => fn () => ProductCategory::inRandomOrder()->value('id') ?? ProductCategory::factory(),
        'unit' => $this->faker->randomElement(['pcs', 'set', 'box', 'unit']),
        'description' => $this->faker->optional()->sentence(),
        'base_price' => $this->faker->optional()->randomFloat(2, 5000, 5000000),
        'is_active' => true,
        'default_rack_id' => null,
    ];
}
```

- [ ] **Step 4: Run existing tests to verify no regression**

```bash
php artisan test tests/Feature/ProductControllerTest.php tests/Feature/CycleControllerTest.php
```

Expected: all existing tests pass.

---

## Task 3: ProductController Updates

**Files:**
- Modify: `app/Http/Controllers/ProductController.php`

- [ ] **Step 1: Write the failing test in ProductControllerTest.php**

Add these two tests to `tests/Feature/ProductControllerTest.php` (add `use App\Models\Rack;` to imports):

```php
public function test_create_passes_racks_to_view(): void
{
    Rack::factory()->create(['code' => 'A-01']);

    $response = $this->actingAs($this->user)->get(route('products.create'));

    $response->assertStatus(200);
    $response->assertSee('A-01');
}

public function test_store_saves_default_rack_id(): void
{
    $rack = Rack::factory()->create();
    $vehicleModel = VehicleModel::factory()->create();
    $supplier = Supplier::factory()->create();
    $category = ProductCategory::factory()->create();

    $response = $this->actingAs($this->user)->post(route('products.store'), [
        'part_number' => 'P-RACK-001',
        'name' => 'Rack Test Part',
        'vehicle_model_id' => $vehicleModel->id,
        'supplier_id' => $supplier->id,
        'category_id' => $category->id,
        'unit' => 'pcs',
        'default_rack_id' => $rack->id,
    ]);

    $this->assertDatabaseHas('products', [
        'part_number' => 'P-RACK-001',
        'default_rack_id' => $rack->id,
    ]);
    $response->assertRedirect(route('products.index'));
}

public function test_update_clears_default_rack_id_when_null(): void
{
    $rack = Rack::factory()->create();
    $product = Product::factory()->create(['default_rack_id' => $rack->id]);
    $vehicleModel = VehicleModel::factory()->create();
    $supplier = Supplier::factory()->create();
    $category = ProductCategory::factory()->create();

    $response = $this->actingAs($this->user)->put(route('products.update', $product), [
        'part_number' => $product->part_number,
        'name' => $product->name,
        'vehicle_model_id' => $vehicleModel->id,
        'supplier_id' => $supplier->id,
        'category_id' => $category->id,
        'unit' => 'pcs',
        'default_rack_id' => null,
    ]);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'default_rack_id' => null,
    ]);
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/ProductControllerTest.php --filter="test_create_passes_racks_to_view|test_store_saves_default_rack_id|test_update_clears_default_rack_id_when_null"
```

Expected: FAIL — tests expect racks in view and default_rack_id in DB, but controller doesn't support them yet.

- [ ] **Step 3: Update ProductController**

Add `use App\Models\Rack;` to imports in `app/Http/Controllers/ProductController.php`.

Update `create()`:

```php
public function create()
{
    return Inertia::render('Master/Products/Create', [
        'vehicleModels' => VehicleModel::orderBy('brand')->orderBy('name')->get(),
        'categories' => ProductCategory::orderBy('name')->get(),
        'suppliers' => Supplier::orderBy('name')->get(),
        'racks' => Rack::orderBy('zone')->orderBy('code')->get(),
    ]);
}
```

Update `store()` validation — add `default_rack_id` field:

```php
$validated = $request->validate([
    'part_number' => 'required|string|max:50|unique:products',
    'name' => 'required|string|max:255',
    'vehicle_model_id' => 'required|exists:vehicle_models,id',
    'supplier_id' => 'required|exists:suppliers,id',
    'category_id' => 'required|exists:product_categories,id',
    'unit' => 'required|string|max:20',
    'description' => 'nullable|string|max:1000',
    'base_price' => 'nullable|numeric|min:0',
    'is_active' => 'boolean',
    'default_rack_id' => 'nullable|exists:racks,id',
]);
```

Update `edit()`:

```php
public function edit(Product $product)
{
    return Inertia::render('Master/Products/Edit', [
        'product' => $product,
        'vehicleModels' => VehicleModel::orderBy('brand')->orderBy('name')->get(),
        'categories' => ProductCategory::orderBy('name')->get(),
        'suppliers' => Supplier::orderBy('name')->get(),
        'racks' => Rack::orderBy('zone')->orderBy('code')->get(),
    ]);
}
```

Update `update()` validation — add `default_rack_id`:

```php
$validated = $request->validate([
    'part_number' => 'required|string|max:50|unique:products,part_number,' . $product->id,
    'name' => 'required|string|max:255',
    'vehicle_model_id' => 'required|exists:vehicle_models,id',
    'supplier_id' => 'required|exists:suppliers,id',
    'category_id' => 'required|exists:product_categories,id',
    'unit' => 'required|string|max:20',
    'description' => 'nullable|string|max:1000',
    'base_price' => 'nullable|numeric|min:0',
    'is_active' => 'boolean',
    'default_rack_id' => 'nullable|exists:racks,id',
]);
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test tests/Feature/ProductControllerTest.php
```

Expected: all tests pass, including the 3 new ones.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_29_000001_add_default_rack_id_to_products_table.php \
        database/migrations/2026_06_29_000002_add_rack_id_to_cycle_items_table.php \
        app/Models/Product.php \
        app/Models/CycleItem.php \
        database/factories/ProductFactory.php \
        app/Http/Controllers/ProductController.php \
        tests/Feature/ProductControllerTest.php
git commit -m "feat: add default_rack_id to products and rack_id to cycle_items"
```

---

## Task 4: Product Frontend (Create, Edit, Show)

**Files:**
- Modify: `resources/js/Pages/Master/Products/Create.tsx`
- Modify: `resources/js/Pages/Master/Products/Edit.tsx`
- Modify: `resources/js/Pages/Master/Products/Show.tsx`

- [ ] **Step 1: Update Products/Create.tsx**

Change the component signature to accept `racks`:

```tsx
export default function Create({ vehicleModels, categories, suppliers, racks }: any) {
```

Add `default_rack_id: ''` to `useForm`:

```tsx
const { data, setData, post, errors } = useForm({
    part_number: '',
    name: '',
    vehicle_model_id: '',
    supplier_id: '',
    category_id: '',
    unit: 'pcs',
    description: '',
    base_price: '',
    is_active: true,
    default_rack_id: '',
});
```

Add a "Rack Default" field inside the form, after the Harga Dasar block and before Deskripsi:

```tsx
<div>
    <Label>Rack Default</Label>
    <SearchableSelect
        options={[
            { value: '', label: 'Tidak ada (pilih saat receiving)' },
            ...racks.map((r: any) => ({ value: r.id, label: `${r.code}${r.zone ? ' — ' + r.zone : ''}` })),
        ]}
        value={data.default_rack_id}
        onChange={(value) => setData('default_rack_id', value as string)}
    />
    {errors.default_rack_id && <p className="mt-1 text-sm text-red-500">{errors.default_rack_id}</p>}
</div>
```

- [ ] **Step 2: Update Products/Edit.tsx**

Change the component signature:

```tsx
export default function Edit({ product, vehicleModels, categories, suppliers, racks }: any) {
```

Add `default_rack_id` to `useForm`:

```tsx
const { data, setData, put, errors } = useForm({
    part_number: product.part_number || '',
    name: product.name || '',
    vehicle_model_id: product.vehicle_model_id || '',
    supplier_id: product.supplier_id || '',
    category_id: product.category_id || '',
    unit: product.unit || 'pcs',
    description: product.description || '',
    base_price: product.base_price || '',
    is_active: product.is_active,
    default_rack_id: product.default_rack_id ? String(product.default_rack_id) : '',
});
```

Add the same "Rack Default" field as in Create, after the Harga Dasar block:

```tsx
<div>
    <Label>Rack Default</Label>
    <SearchableSelect
        options={[
            { value: '', label: 'Tidak ada (pilih saat receiving)' },
            ...racks.map((r: any) => ({ value: r.id, label: `${r.code}${r.zone ? ' — ' + r.zone : ''}` })),
        ]}
        value={data.default_rack_id}
        onChange={(value) => setData('default_rack_id', value as string)}
    />
    {errors.default_rack_id && <p className="mt-1 text-sm text-red-500">{errors.default_rack_id}</p>}
</div>
```

- [ ] **Step 3: Update Products/Show.tsx**

Add a "Rack Default" entry in the `<dl>` block, after the Status entry:

```tsx
<div>
    <dt className="text-xs font-medium text-[#6C757D] uppercase tracking-wider mb-1">Rack Default</dt>
    <dd className="text-sm text-[#1A1D23]">
        {product.default_rack
            ? <span className="font-mono">{product.default_rack.code}</span>
            : <span className="text-gray-400">-</span>}
    </dd>
</div>
```

Update `ProductController::show()` to load the `defaultRack` relation (in `app/Http/Controllers/ProductController.php`):

```php
public function show(Product $product)
{
    return Inertia::render('Master/Products/Show', [
        'product' => $product->load(['vehicleModel', 'supplier', 'category', 'defaultRack']),
    ]);
}
```

- [ ] **Step 4: Verify frontend renders without errors**

Start the dev server and navigate to `/products/create` and `/products/{id}/edit`. Confirm the "Rack Default" dropdown appears. Save a product with a default rack and verify it shows in the Show page.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Master/Products/Create.tsx \
        resources/js/Pages/Master/Products/Edit.tsx \
        resources/js/Pages/Master/Products/Show.tsx \
        app/Http/Controllers/ProductController.php
git commit -m "feat: add default rack selector to product create/edit/show"
```

---

## Task 5: CycleController Updates

**Files:**
- Modify: `app/Http/Controllers/CycleController.php`

- [ ] **Step 1: Write the failing test**

Add these tests to `tests/Feature/CycleControllerTest.php`:

```php
public function test_receive_saves_rack_id_to_cycle_item(): void
{
    $rack = Rack::factory()->create();
    $cycle = Cycle::factory()->create(['status' => 'draft']);
    $product = Product::factory()->create();
    $item = CycleItem::factory()->create([
        'cycle_id' => $cycle->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'received_quantity' => 0,
    ]);

    $this->actingAs($this->user)->post(route('cycles.receive', $cycle), [
        'items' => [[
            'id' => $item->id,
            'received_quantity' => 5,
            'rack_id' => $rack->id,
            'notes' => null,
        ]],
    ]);

    $this->assertDatabaseHas('cycle_items', [
        'id' => $item->id,
        'rack_id' => $rack->id,
    ]);
}

public function test_show_passes_last_used_racks(): void
{
    $rack = Rack::factory()->create(['code' => 'H-01']);
    $product = Product::factory()->create();

    // Prior completed cycle with rack_id recorded
    $priorCycle = Cycle::factory()->create(['status' => 'completed']);
    CycleItem::factory()->create([
        'cycle_id' => $priorCycle->id,
        'product_id' => $product->id,
        'rack_id' => $rack->id,
        'received_quantity' => 5,
    ]);

    // Current cycle with same product
    $cycle = Cycle::factory()->create(['status' => 'draft']);
    CycleItem::factory()->create([
        'cycle_id' => $cycle->id,
        'product_id' => $product->id,
        'quantity' => 10,
    ]);

    $response = $this->actingAs($this->user)->get(route('cycles.show', $cycle));

    // Verifies show() doesn't crash with the new lastUsedRacks query
    $response->assertStatus(200);
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/CycleControllerTest.php --filter="test_receive_saves_rack_id_to_cycle_item|test_show_passes_last_used_racks"
```

Expected: FAIL — `cycle_items.rack_id` is null after receive (not yet saved), and H-01 rack may not appear via lastUsedRacks prop.

- [ ] **Step 3: Update CycleController::show()**

Replace the current `show()` method body in `app/Http/Controllers/CycleController.php`:

```php
public function show(Cycle $cycle)
{
    $cycle->load(['supplier', 'items.product.vehicleModel', 'items.product.category', 'items.product.defaultRack']);

    $productIds = $cycle->items->pluck('product_id')->toArray();
    $lastUsedRacks = CycleItem::whereIn('product_id', $productIds)
        ->whereNotNull('rack_id')
        ->where('cycle_id', '!=', $cycle->id)
        ->orderByDesc('updated_at')
        ->get()
        ->unique('product_id')
        ->pluck('rack_id', 'product_id');

    return Inertia::render('Transactions/Cycles/Show', [
        'cycle' => $cycle,
        'racks' => Rack::orderBy('zone')->orderBy('code')->get(),
        'lastUsedRacks' => $lastUsedRacks,
    ]);
}
```

- [ ] **Step 4: Update CycleController::receive()**

Inside the `foreach ($validated['items'] as $itemData)` loop, add `rack_id` to the `$item->update()` call:

```php
$item->update([
    'received_quantity' => $itemData['received_quantity'],
    'rack_id' => $itemData['rack_id'],
    'notes' => $itemData['notes'] ?? null,
]);
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test tests/Feature/CycleControllerTest.php
```

Expected: all tests pass, including the 2 new ones.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/CycleController.php \
        tests/Feature/CycleControllerTest.php
git commit -m "feat: pass lastUsedRacks to cycle show and save rack_id on receive"
```

---

## Task 6: Cycles/Show.tsx — Pre-fill + Badge

**Files:**
- Modify: `resources/js/Pages/Transactions/Cycles/Show.tsx`

- [ ] **Step 1: Update component signature and useState initialization**

Change the component signature to accept `lastUsedRacks`:

```tsx
export default function Show({ cycle, racks, lastUsedRacks }: any) {
```

Replace the `useState` initialization for `items` (currently initializes `rack_id: ''`):

```tsx
const [items, setItems] = useState<any[]>(
    cycle.items.map((item: any) => {
        let rackId = '';
        let rackSource: 'default' | 'history' | 'none' = 'none';

        if (item.product?.default_rack_id) {
            rackId = String(item.product.default_rack_id);
            rackSource = 'default';
        } else if (lastUsedRacks[item.product_id]) {
            rackId = String(lastUsedRacks[item.product_id]);
            rackSource = 'history';
        }

        return {
            id: item.id,
            received_quantity: item.received_quantity || 0,
            rack_id: rackId,
            rack_source: rackSource,
            notes: item.notes || '',
        };
    })
);
```

- [ ] **Step 2: Update the Rak table cell to show badge**

Find the `<td>` that renders the SearchableSelect for rack (currently line 91 area). Replace it with:

```tsx
<td className="px-3 py-2">
    <div className="flex flex-col gap-1">
        <SearchableSelect
            options={racks.map((r: any) => ({ value: r.id, label: r.code }))}
            value={items[i].rack_id}
            onChange={(v) => updateItem(i, 'rack_id', v as string)}
        />
        {items[i].rack_source === 'default' && (
            <span className="self-start px-1.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">Default</span>
        )}
        {items[i].rack_source === 'history' && (
            <span className="self-start px-1.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-600">Terakhir</span>
        )}
    </div>
</td>
```

- [ ] **Step 3: Verify in browser**

Start the dev server, create a cycle, set a default rack on one of the products (or receive a prior cycle to build history), then open the Show page and click "Terima Barang". Confirm:
- Product with `default_rack_id` set → rack pre-filled + "Default" badge (blue)
- Product with prior receiving history → rack pre-filled + "Terakhir" badge (gray)
- Product with no default/history → rack empty, no badge

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Transactions/Cycles/Show.tsx
git commit -m "feat: auto-fill rack in receiving form from default or history"
```

---

## Task 7: Full Test Suite

- [ ] **Step 1: Run all feature tests**

```bash
php artisan test tests/Feature/
```

Expected: all tests pass with no failures or errors.

- [ ] **Step 2: If any test fails, read the failure output and fix the specific issue**

Common causes:
- `ProductFactory` doesn't have `default_rack_id` → add `'default_rack_id' => null` to factory definition
- `CycleItemFactory` doesn't have `rack_id` → the factory doesn't need it (it's nullable and optional)
- Migration not run → run `php artisan migrate`
