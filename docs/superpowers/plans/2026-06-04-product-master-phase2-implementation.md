# Product Master Phase 2 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement Product master data with vehicle models and categories, reorganize Suppliers into Master Data group, and create full CRUD pages for Products.

**Architecture:** Three new database tables (`vehicle_models`, `product_categories`, `products`) with Eloquent models, ProductController for CRUD, Inertia React pages under `Master/` folder. Supplier pages moved from `Pages/Suppliers/` to `Pages/Master/Suppliers/`.

**Tech Stack:** Laravel 11, PHP 8.2+, MySQL, Inertia.js + React + TypeScript, Tailwind CSS (Tailadmin), PHPUnit

---

## File Structure

### Files to Create
```
database/migrations/
  └── 2026_06_04_000003_create_vehicle_models_table.php
  └── 2026_06_04_000004_create_product_categories_table.php
  └── 2026_06_04_000005_create_products_table.php

app/Models/
  └── VehicleModel.php
  └── ProductCategory.php
  └── Product.php

database/factories/
  └── VehicleModelFactory.php
  └── ProductCategoryFactory.php
  └── ProductFactory.php

app/Http/Controllers/
  └── ProductController.php

resources/js/Pages/Master/
  └── Products/
      ├── Index.tsx
      ├── Create.tsx
      ├── Edit.tsx
      └── Show.tsx

tests/Feature/
  └── ProductControllerTest.php

database/seeders/
  └── ProductSeeder.php
```

### Files to Move
```
resources/js/Pages/Suppliers/*  →  resources/js/Pages/Master/Suppliers/*
```

### Files to Modify
```
app/Models/Supplier.php                        [add products() relationship]
app/Http/Controllers/SupplierController.php    [update Inertia render paths]
routes/web.php                                  [add product resource route]
```

---

## Task 1: Create VehicleModels Migration

**Files:**
- Create: `database/migrations/2026_06_04_000003_create_vehicle_models_table.php`

- [ ] **Step 1: Create migration file**

Run:
```bash
php artisan make:migration create_vehicle_models_table --create=vehicle_models
```

- [ ] **Step 2: Replace migration content**

Edit the new migration file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('brand', 100);
            $table->timestamps();

            $table->unique(['name', 'brand']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_models');
    }
};
```

- [ ] **Step 3: Verify migration file**

```bash
ls -la database/migrations/*vehicle_models*
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/*_create_vehicle_models_table.php
git commit -m "feat: create vehicle_models table migration"
```

---

## Task 2: Create ProductCategories Migration

**Files:**
- Create: `database/migrations/2026_06_04_000004_create_product_categories_table.php`

- [ ] **Step 1: Create migration file**

Run:
```bash
php artisan make:migration create_product_categories_table --create=product_categories
```

- [ ] **Step 2: Replace migration content**

Edit the new migration file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
```

- [ ] **Step 3: Verify migration file**

```bash
ls -la database/migrations/*product_categories*
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/*_create_product_categories_table.php
git commit -m "feat: create product_categories table migration"
```

---

## Task 3: Create Products Migration

**Files:**
- Create: `database/migrations/2026_06_04_000005_create_products_table.php`

- [ ] **Step 1: Create migration file**

Run:
```bash
php artisan make:migration create_products_table --create=products
```

- [ ] **Step 2: Replace migration content**

Edit the new migration file. **Important:** This migration must run AFTER suppliers, vehicle_models, and product_categories migrations.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('part_number', 50)->unique();
            $table->string('name');
            $table->foreignId('vehicle_model_id')
                  ->constrained('vehicle_models')
                  ->restrictOnDelete();
            $table->foreignId('supplier_id')
                  ->constrained('suppliers')
                  ->restrictOnDelete();
            $table->foreignId('category_id')
                  ->constrained('product_categories')
                  ->restrictOnDelete();
            $table->string('unit', 20)->default('pcs');
            $table->text('description')->nullable();
            $table->decimal('base_price', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('part_number');
            $table->index('supplier_id');
            $table->index('category_id');
            $table->index('vehicle_model_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

- [ ] **Step 3: Verify migration file**

```bash
ls -la database/migrations/*products*
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/*_create_products_table.php
git commit -m "feat: create products table migration"
```

---

## Task 4: Create VehicleModel Model

**Files:**
- Create: `app/Models/VehicleModel.php`

- [ ] **Step 1: Generate model**

Run:
```bash
php artisan make:model VehicleModel
```

- [ ] **Step 2: Replace model content**

Edit `app/Models/VehicleModel.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'brand',
    ];

    /**
     * Get all products for this vehicle model.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
```

- [ ] **Step 3: Verify**

```bash
grep "class VehicleModel" app/Models/VehicleModel.php
```

Expected: `class VehicleModel extends Model`

- [ ] **Step 4: Commit**

```bash
git add app/Models/VehicleModel.php
git commit -m "feat: create VehicleModel model with products relationship"
```

---

## Task 5: Create ProductCategory Model

**Files:**
- Create: `app/Models/ProductCategory.php`

- [ ] **Step 1: Generate model**

Run:
```bash
php artisan make:model ProductCategory
```

- [ ] **Step 2: Replace model content**

Edit `app/Models/ProductCategory.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Get all products in this category.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
```

- [ ] **Step 3: Verify**

```bash
grep "class ProductCategory" app/Models/ProductCategory.php
```

Expected: `class ProductCategory extends Model`

- [ ] **Step 4: Commit**

```bash
git add app/Models/ProductCategory.php
git commit -m "feat: create ProductCategory model with products relationship"
```

---

## Task 6: Create Product Model

**Files:**
- Create: `app/Models/Product.php`

- [ ] **Step 1: Generate model**

Run:
```bash
php artisan make:model Product
```

- [ ] **Step 2: Replace model content**

Edit `app/Models/Product.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

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
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'base_price' => 'decimal:2',
        ];
    }

    /**
     * Get the vehicle model this product belongs to.
     */
    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class);
    }

    /**
     * Get the supplier that provides this product.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the category this product belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }
}
```

- [ ] **Step 3: Verify**

```bash
grep "class Product" app/Models/Product.php
```

Expected: `class Product extends Model`

- [ ] **Step 4: Commit**

```bash
git add app/Models/Product.php
git commit -m "feat: create Product model with relationships"
```

---

## Task 7: Update Supplier Model (add products relationship)

**Files:**
- Modify: `app/Models/Supplier.php`

- [ ] **Step 1: Add products() method**

Open `app/Models/Supplier.php`. Find the `primaryAddress()` method closing brace. Add after it:

```php
    /**
     * Get all products supplied by this supplier.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
```

Make sure the import is at the top:
```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

(Note: `HasMany` is already imported — no change needed to imports.)

- [ ] **Step 2: Verify**

```bash
grep "products()" app/Models/Supplier.php
```

Expected: Shows the method definition.

- [ ] **Step 3: Commit**

```bash
git add app/Models/Supplier.php
git commit -m "feat: add products relationship to Supplier model"
```

---

## Task 8: Create VehicleModelFactory

**Files:**
- Create: `database/factories/VehicleModelFactory.php`

- [ ] **Step 1: Generate factory**

Run:
```bash
php artisan make:factory VehicleModelFactory --model=VehicleModel
```

- [ ] **Step 2: Replace factory content**

Edit `database/factories/VehicleModelFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\VehicleModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleModel>
 */
class VehicleModelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'Fortuner VRZ', 'Avanza G', 'Innova Zenix', 'Hilux',
                'CR-V Turbo', 'HR-V SE', 'Civic RS',
                'Xpander Cross', 'Pajero Sport', 'L300',
            ]),
            'brand' => $this->faker->randomElement([
                'Toyota', 'Honda', 'Mitsubishi', 'Daihatsu', 'Suzuki',
            ]),
        ];
    }
}
```

- [ ] **Step 3: Verify**

```bash
grep "class VehicleModelFactory" database/factories/VehicleModelFactory.php
```

- [ ] **Step 4: Commit**

```bash
git add database/factories/VehicleModelFactory.php
git commit -m "feat: create VehicleModelFactory"
```

---

## Task 9: Create ProductCategoryFactory

**Files:**
- Create: `database/factories/ProductCategoryFactory.php`

- [ ] **Step 1: Generate factory**

Run:
```bash
php artisan make:factory ProductCategoryFactory --model=ProductCategory
```

- [ ] **Step 2: Replace factory content**

Edit `database/factories/ProductCategoryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'Body Parts', 'Engine Components', 'Electrical',
                'Interior', 'Suspension & Brakes', 'Exhaust System',
                'Cooling System', 'Transmission',
            ]),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
```

- [ ] **Step 3: Verify**

```bash
grep "class ProductCategoryFactory" database/factories/ProductCategoryFactory.php
```

- [ ] **Step 4: Commit**

```bash
git add database/factories/ProductCategoryFactory.php
git commit -m "feat: create ProductCategoryFactory"
```

---

## Task 10: Create ProductFactory

**Files:**
- Create: `database/factories/ProductFactory.php`

- [ ] **Step 1: Generate factory**

Run:
```bash
php artisan make:factory ProductFactory --model=Product
```

- [ ] **Step 2: Replace factory content**

Edit `database/factories/ProductFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\VehicleModel;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'part_number' => strtoupper($this->faker->unique()->bothify('P####-??###')),
            'name' => $this->faker->words(3, true),
            'vehicle_model_id' => VehicleModel::factory(),
            'supplier_id' => Supplier::factory(),
            'category_id' => ProductCategory::factory(),
            'unit' => $this->faker->randomElement(['pcs', 'set', 'box', 'unit']),
            'description' => $this->faker->optional()->sentence(),
            'base_price' => $this->faker->optional()->randomFloat(2, 5000, 5000000),
            'is_active' => true,
        ];
    }
}
```

- [ ] **Step 3: Verify**

```bash
grep "class ProductFactory" database/factories/ProductFactory.php
```

- [ ] **Step 4: Commit**

```bash
git add database/factories/ProductFactory.php
git commit -m "feat: create ProductFactory"
```

---

## Task 11: Move Supplier Pages to Master/Suppliers/

**Files:**
- Move: `resources/js/Pages/Suppliers/*` → `resources/js/Pages/Master/Suppliers/*`

- [ ] **Step 1: Create target directory and move files**

Run:
```bash
mkdir -p resources/js/Pages/Master/Suppliers
mv resources/js/Pages/Suppliers/* resources/js/Pages/Master/Suppliers/
rmdir resources/js/Pages/Suppliers
```

- [ ] **Step 2: Verify old location is empty, new has files**

```bash
test ! -d resources/js/Pages/Suppliers && echo "Old dir removed OK"
ls resources/js/Pages/Master/Suppliers/
```

Expected: Shows `Index.tsx`, `Create.tsx`, `Edit.tsx`, `Show.tsx`

- [ ] **Step 3: Update internal imports in moved files**

The `AppLayout` import path changes from `../../Tailadmin/layout/AppLayout` to `../../../Tailadmin/layout/AppLayout` (one level deeper).

Edit each file in `resources/js/Pages/Master/Suppliers/` and change:

```
import AppLayout from '../../Tailadmin/layout/AppLayout';
```

to:

```
import AppLayout from '../../../Tailadmin/layout/AppLayout';
```

Do the same for all other `../../Tailadmin/` imports → `../../../Tailadmin/`.

Files to update:
- `Index.tsx` — `AppLayout`, `PageBreadcrumb`, `ComponentCard`, `Button` imports
- `Create.tsx` — `AppLayout`, `PageBreadcrumb`, `ComponentCard`, `Button`, `Input`, `Label` imports
- `Edit.tsx` — same as Create
- `Show.tsx` — `AppLayout`, `PageBreadcrumb`, `ComponentCard`, `Button` imports

- [ ] **Step 4: Build and verify**

```bash
npm run build 2>&1
```

Expected: Build completes without errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Suppliers/ resources/js/Pages/Master/Suppliers/
git commit -m "refactor: move Supplier pages to Master/Suppliers/ folder"
```

---

## Task 12: Update SupplierController Render Paths

**Files:**
- Modify: `app/Http/Controllers/SupplierController.php`

- [ ] **Step 1: Update all Inertia::render paths**

Open `app/Http/Controllers/SupplierController.php`. Change all four render calls:

| Method | Old Path | New Path |
|--------|----------|----------|
| `index()` | `Suppliers/Index` | `Master/Suppliers/Index` |
| `create()` | `Suppliers/Create` | `Master/Suppliers/Create` |
| `show()` | `Suppliers/Show` | `Master/Suppliers/Show` |
| `edit()` | `Suppliers/Edit` | `Master/Suppliers/Edit` |

```php
// index() — line ~19
return Inertia::render('Master/Suppliers/Index', [

// create() — line ~29
return Inertia::render('Master/Suppliers/Create');

// show() — line ~74
return Inertia::render('Master/Suppliers/Show', [

// edit() — line ~84
return Inertia::render('Master/Suppliers/Edit', [
```

- [ ] **Step 2: Verify with grep**

```bash
grep "Inertia::render" app/Http/Controllers/SupplierController.php
```

Expected: All paths show `Master/Suppliers/...`

- [ ] **Step 3: Run tests**

```bash
php artisan test tests/Feature/SupplierControllerTest.php 2>&1
```

Expected: 10/10 passed.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/SupplierController.php
git commit -m "refactor: update SupplierController render paths to Master/Suppliers/"
```

---

## Task 13: Create ProductController

**Files:**
- Create: `app/Http/Controllers/ProductController.php`

- [ ] **Step 1: Generate controller**

Run:
```bash
php artisan make:controller ProductController --model=Product --resource
```

- [ ] **Step 2: Replace controller content**

Edit `app/Http/Controllers/ProductController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\VehicleModel;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request)
    {
        $products = Product::with(['vehicleModel', 'supplier', 'category'])
            ->when($request->search, function ($query, $search) {
                $query->where('part_number', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%");
            })
            ->when($request->category_id, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($request->supplier_id, function ($query, $supplierId) {
                $query->where('supplier_id', $supplierId);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Master/Products/Index', [
            'products' => $products,
            'categories' => ProductCategory::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'filters' => $request->only(['search', 'category_id', 'supplier_id']),
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create()
    {
        return Inertia::render('Master/Products/Create', [
            'vehicleModels' => VehicleModel::orderBy('brand')->orderBy('name')->get(),
            'categories' => ProductCategory::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request)
    {
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
        ]);

        Product::create($validated);

        return redirect()->route('products.index')
                       ->with('success', 'Product created successfully.');
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product)
    {
        return Inertia::render('Master/Products/Show', [
            'product' => $product->load(['vehicleModel', 'supplier', 'category']),
        ]);
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product)
    {
        return Inertia::render('Master/Products/Edit', [
            'product' => $product,
            'vehicleModels' => VehicleModel::orderBy('brand')->orderBy('name')->get(),
            'categories' => ProductCategory::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, Product $product)
    {
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
        ]);

        $product->update($validated);

        return redirect()->route('products.show', $product)
                       ->with('success', 'Product updated successfully.');
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('products.index')
                       ->with('success', 'Product deleted successfully.');
    }
}
```

- [ ] **Step 3: Verify controller methods**

```bash
grep "public function" app/Http/Controllers/ProductController.php | wc -l
```

Expected: 7 (index, create, store, show, edit, update, destroy)

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/ProductController.php
git commit -m "feat: create ProductController with resource methods"
```

---

## Task 14: Add Product Routes

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Add import**

Add at the top of `routes/web.php` after the `SupplierController` import:

```php
use App\Http\Controllers\ProductController;
```

- [ ] **Step 2: Add resource route**

Add after the `suppliers` resource route:

```php
    Route::resource('suppliers', SupplierController::class);
    Route::resource('products', ProductController::class);
```

- [ ] **Step 3: Verify routes**

```bash
php artisan route:list 2>&1 | grep "products"
```

Expected: 7 routes (index, create, store, show, edit, update, destroy)

- [ ] **Step 4: Commit**

```bash
git add routes/web.php
git commit -m "feat: add product resource routes"
```

---

## Task 15: Create Product Index Page

**Files:**
- Create: `resources/js/Pages/Master/Products/Index.tsx`

- [ ] **Step 1: Create directory**

```bash
mkdir -p resources/js/Pages/Master/Products
```

- [ ] **Step 2: Write page**

Create `resources/js/Pages/Master/Products/Index.tsx`:

```tsx
import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';

export default function Index({ products, categories, suppliers, filters }: any) {
    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this product?')) {
            router.delete(route('products.destroy', id));
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        const formData = new FormData(e.target as HTMLFormElement);
        router.get(route('products.index'), {
            search: formData.get('search') as string || '',
            category_id: formData.get('category_id') as string || '',
            supplier_id: formData.get('supplier_id') as string || '',
        }, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title="Products" />
            <PageBreadcrumb pageTitle="Products" />

            <ComponentCard title="Product List">
                <div className="mb-4 flex gap-3 flex-wrap items-end">
                    <form onSubmit={handleSearch} className="flex gap-3 flex-wrap items-end w-full">
                        <div className="min-w-[200px]">
                            <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
                            <Input
                                type="text"
                                name="search"
                                defaultValue={filters?.search || ''}
                                placeholder="Part number or name..."
                            />
                        </div>
                        <div className="min-w-[180px]">
                            <label className="block text-xs font-medium text-gray-500 mb-1">Category</label>
                            <SearchableSelect
                                options={[
                                    { value: '', label: 'All Categories' },
                                    ...categories.map((c: any) => ({ value: c.id, label: c.name }))
                                ]}
                                value={filters?.category_id || ''}
                                onChange={(value) => {
                                    router.get(route('products.index'), {
                                        ...filters,
                                        category_id: value as string,
                                    }, { preserveState: true });
                                }}
                            />
                        </div>
                        <div className="min-w-[180px]">
                            <label className="block text-xs font-medium text-gray-500 mb-1">Supplier</label>
                            <SearchableSelect
                                options={[
                                    { value: '', label: 'All Suppliers' },
                                    ...suppliers.map((s: any) => ({ value: s.id, label: s.name }))
                                ]}
                                value={filters?.supplier_id || ''}
                                onChange={(value) => {
                                    router.get(route('products.index'), {
                                        ...filters,
                                        supplier_id: value as string,
                                    }, { preserveState: true });
                                }}
                            />
                        </div>
                        <div>
                            <Button type="submit" variant="outline">Filter</Button>
                        </div>
                    </form>
                </div>

                <div className="mb-3">
                    <Link href={route('products.create')}>
                        <Button>Add Product</Button>
                    </Link>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Part Number</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Model</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            {products.data.map((product: any) => (
                                <tr key={product.id}>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm font-mono">{product.part_number}</td>
                                    <td className="px-4 py-3 text-sm">{product.name}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{product.vehicle_model?.name || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{product.supplier?.name || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{product.category?.name || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {product.base_price ? `Rp ${Number(product.base_price).toLocaleString('id-ID')}` : '-'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                        <Link href={route('products.show', product.id)} className="text-brand-500 hover:text-brand-700 mr-2">View</Link>
                                        <Link href={route('products.edit', product.id)} className="text-brand-500 hover:text-brand-700 mr-2">Edit</Link>
                                        <button onClick={() => handleDelete(product.id)} className="text-red-500 hover:text-red-700">Delete</button>
                                    </td>
                                </tr>
                            ))}
                            {products.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-sm text-gray-500">No products found.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {products.total > products.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">
                            Showing {products.from || 0} to {products.to || 0} of {products.total}
                        </div>
                        <div className="flex gap-2">
                            {products.prev_page_url ? (
                                <Link href={products.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Prev</Link>
                            ) : (
                                <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Prev</span>
                            )}
                            <span className="px-3 py-1 text-sm">Page {products.current_page} of {products.last_page}</span>
                            {products.next_page_url ? (
                                <Link href={products.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Next</Link>
                            ) : (
                                <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Next</span>
                            )}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </AppLayout>
    );
}
```

- [ ] **Step 3: Build frontend**

```bash
npm run build 2>&1
```

Expected: No errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Master/Products/Index.tsx
git commit -m "feat: create Product Index page with search and filters"
```

---

## Task 16: Create Product Create Page

**Files:**
- Create: `resources/js/Pages/Master/Products/Create.tsx`

- [ ] **Step 1: Write page**

Create `resources/js/Pages/Master/Products/Create.tsx`:

```tsx
import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';

export default function Create({ vehicleModels, categories, suppliers }: any) {
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
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('products.store'));
    };

    return (
        <AppLayout>
            <Head title="Add Product" />
            <PageBreadcrumb pageTitle="Add Product" />

            <ComponentCard title="New Product">
                <form onSubmit={handleSubmit} className="space-y-4 max-w-2xl">
                    <div>
                        <Label>Part Number *</Label>
                        <Input type="text" value={data.part_number} onChange={(e) => setData('part_number', e.target.value)} placeholder="e.g. P5188-0KA03" />
                        {errors.part_number && <p className="mt-1 text-sm text-red-500">{errors.part_number}</p>}
                    </div>
                    <div>
                        <Label>Part Name *</Label>
                        <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g. Grade Emblem (VRZ)" />
                        {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Vehicle Model *</Label>
                            <SearchableSelect
                                options={vehicleModels.map((m: any) => ({ value: m.id, label: `${m.name} (${m.brand})` }))}
                                value={data.vehicle_model_id}
                                onChange={(value) => setData('vehicle_model_id', value as string)}
                            />
                            {errors.vehicle_model_id && <p className="mt-1 text-sm text-red-500">{errors.vehicle_model_id}</p>}
                        </div>
                        <div>
                            <Label>Supplier *</Label>
                            <SearchableSelect
                                options={suppliers.map((s: any) => ({ value: s.id, label: s.name }))}
                                value={data.supplier_id}
                                onChange={(value) => setData('supplier_id', value as string)}
                            />
                            {errors.supplier_id && <p className="mt-1 text-sm text-red-500">{errors.supplier_id}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Category *</Label>
                            <SearchableSelect
                                options={categories.map((c: any) => ({ value: c.id, label: c.name }))}
                                value={data.category_id}
                                onChange={(value) => setData('category_id', value as string)}
                            />
                            {errors.category_id && <p className="mt-1 text-sm text-red-500">{errors.category_id}</p>}
                        </div>
                        <div>
                            <Label>Unit *</Label>
                            <SearchableSelect
                                options={[
                                    { value: 'pcs', label: 'Pcs' },
                                    { value: 'set', label: 'Set' },
                                    { value: 'box', label: 'Box' },
                                    { value: 'unit', label: 'Unit' },
                                ]}
                                value={data.unit}
                                onChange={(value) => setData('unit', value as string)}
                            />
                            {errors.unit && <p className="mt-1 text-sm text-red-500">{errors.unit}</p>}
                        </div>
                    </div>

                    <div>
                        <Label>Base Price (Rp)</Label>
                        <Input type="number" value={data.base_price} onChange={(e) => setData('base_price', e.target.value)} placeholder="e.g. 150000" />
                        {errors.base_price && <p className="mt-1 text-sm text-red-500">{errors.base_price}</p>}
                    </div>

                    <div>
                        <Label>Description</Label>
                        <textarea
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            rows={3}
                            className="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-3 py-2 text-sm"
                            placeholder="Additional notes..."
                        />
                        {errors.description && <p className="mt-1 text-sm text-red-500">{errors.description}</p>}
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit">Save Product</Button>
                        <Button type="button" variant="outline" onClick={() => window.history.back()}>Cancel</Button>
                    </div>
                </form>
            </ComponentCard>
        </AppLayout>
    );
}
```

- [ ] **Step 2: Build frontend**

```bash
npm run build 2>&1
```

Expected: No errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Master/Products/Create.tsx
git commit -m "feat: create Product Create page with dropdowns"
```

---

## Task 17: Create Product Edit Page

**Files:**
- Create: `resources/js/Pages/Master/Products/Edit.tsx`

- [ ] **Step 1: Write page**

Create `resources/js/Pages/Master/Products/Edit.tsx`:

```tsx
import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import Label from '../../../Tailadmin/components/form/Label';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';

export default function Edit({ product, vehicleModels, categories, suppliers }: any) {
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
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('products.update', product.id));
    };

    return (
        <AppLayout>
            <Head title={`Edit Product - ${product.part_number}`} />
            <PageBreadcrumb pageTitle={`Edit: ${product.part_number}`} />

            <ComponentCard title="Edit Product">
                <form onSubmit={handleSubmit} className="space-y-4 max-w-2xl">
                    <div>
                        <Label>Part Number *</Label>
                        <Input type="text" value={data.part_number} onChange={(e) => setData('part_number', e.target.value)} />
                        {errors.part_number && <p className="mt-1 text-sm text-red-500">{errors.part_number}</p>}
                    </div>
                    <div>
                        <Label>Part Name *</Label>
                        <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Vehicle Model *</Label>
                            <SearchableSelect
                                options={vehicleModels.map((m: any) => ({ value: m.id, label: `${m.name} (${m.brand})` }))}
                                value={data.vehicle_model_id}
                                onChange={(value) => setData('vehicle_model_id', value as string)}
                            />
                            {errors.vehicle_model_id && <p className="mt-1 text-sm text-red-500">{errors.vehicle_model_id}</p>}
                        </div>
                        <div>
                            <Label>Supplier *</Label>
                            <SearchableSelect
                                options={suppliers.map((s: any) => ({ value: s.id, label: s.name }))}
                                value={data.supplier_id}
                                onChange={(value) => setData('supplier_id', value as string)}
                            />
                            {errors.supplier_id && <p className="mt-1 text-sm text-red-500">{errors.supplier_id}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Category *</Label>
                            <SearchableSelect
                                options={categories.map((c: any) => ({ value: c.id, label: c.name }))}
                                value={data.category_id}
                                onChange={(value) => setData('category_id', value as string)}
                            />
                            {errors.category_id && <p className="mt-1 text-sm text-red-500">{errors.category_id}</p>}
                        </div>
                        <div>
                            <Label>Unit *</Label>
                            <SearchableSelect
                                options={[
                                    { value: 'pcs', label: 'Pcs' },
                                    { value: 'set', label: 'Set' },
                                    { value: 'box', label: 'Box' },
                                    { value: 'unit', label: 'Unit' },
                                ]}
                                value={data.unit}
                                onChange={(value) => setData('unit', value as string)}
                            />
                            {errors.unit && <p className="mt-1 text-sm text-red-500">{errors.unit}</p>}
                        </div>
                    </div>

                    <div>
                        <Label>Base Price (Rp)</Label>
                        <Input type="number" value={data.base_price} onChange={(e) => setData('base_price', e.target.value)} />
                        {errors.base_price && <p className="mt-1 text-sm text-red-500">{errors.base_price}</p>}
                    </div>

                    <div>
                        <Label>Description</Label>
                        <textarea
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            rows={3}
                            className="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-3 py-2 text-sm"
                        />
                        {errors.description && <p className="mt-1 text-sm text-red-500">{errors.description}</p>}
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit">Update Product</Button>
                        <Button type="button" variant="outline" onClick={() => window.history.back()}>Cancel</Button>
                    </div>
                </form>
            </ComponentCard>
        </AppLayout>
    );
}
```

- [ ] **Step 2: Build frontend**

```bash
npm run build 2>&1
```

Expected: No errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Master/Products/Edit.tsx
git commit -m "feat: create Product Edit page"
```

---

## Task 18: Create Product Show Page

**Files:**
- Create: `resources/js/Pages/Master/Products/Show.tsx`

- [ ] **Step 1: Write page**

Create `resources/js/Pages/Master/Products/Show.tsx`:

```tsx
import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';

export default function Show({ product }: any) {
    return (
        <AppLayout>
            <Head title={`Product - ${product.part_number}`} />
            <PageBreadcrumb pageTitle={product.part_number} />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <ComponentCard title="Product Information">
                    <dl className="space-y-3">
                        <div>
                            <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Part Number</dt>
                            <dd className="text-sm font-mono">{product.part_number}</dd>
                        </div>
                        <div>
                            <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                            <dd className="text-sm">{product.name}</dd>
                        </div>
                        <div>
                            <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Vehicle Model</dt>
                            <dd className="text-sm">{product.vehicle_model ? `${product.vehicle_model.name} (${product.vehicle_model.brand})` : '-'}</dd>
                        </div>
                        <div>
                            <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Category</dt>
                            <dd className="text-sm">{product.category?.name || '-'}</dd>
                        </div>
                        <div>
                            <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Unit</dt>
                            <dd className="text-sm">{product.unit}</dd>
                        </div>
                        <div>
                            <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Base Price</dt>
                            <dd className="text-sm">{product.base_price ? `Rp ${Number(product.base_price).toLocaleString('id-ID')}` : '-'}</dd>
                        </div>
                        <div>
                            <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                            <dd className="text-sm">
                                <span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${product.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'}`}>
                                    {product.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </dd>
                        </div>
                        {product.description && (
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Description</dt>
                                <dd className="text-sm">{product.description}</dd>
                            </div>
                        )}
                    </dl>

                    <div className="mt-6 flex gap-2">
                        <Link href={route('products.edit', product.id)}>
                            <Button>Edit</Button>
                        </Link>
                        <Link href={route('products.index')}>
                            <Button variant="outline">Back to List</Button>
                        </Link>
                    </div>
                </ComponentCard>

                <ComponentCard title="Supplier Information">
                    {product.supplier ? (
                        <dl className="space-y-3">
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Supplier Name</dt>
                                <dd className="text-sm">
                                    <Link href={route('suppliers.show', product.supplier.id)} className="text-brand-500 hover:text-brand-700">
                                        {product.supplier.name}
                                    </Link>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Contact Person</dt>
                                <dd className="text-sm">{product.supplier.contact_person || '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                                <dd className="text-sm">{product.supplier.email}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">Phone</dt>
                                <dd className="text-sm">{product.supplier.phone || '-'}</dd>
                            </div>
                        </dl>
                    ) : (
                        <p className="text-sm text-gray-500">No supplier assigned.</p>
                    )}
                </ComponentCard>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 2: Build frontend**

```bash
npm run build 2>&1
```

Expected: No errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Master/Products/Show.tsx
git commit -m "feat: create Product Show page with supplier info"
```

---

## Task 19: Create ProductControllerTest

**Files:**
- Create: `tests/Feature/ProductControllerTest.php`

- [ ] **Step 1: Write test file**

Create `tests/Feature/ProductControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\VehicleModel;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Test index displays all products
     */
    public function test_index_displays_products(): void
    {
        $products = Product::factory(3)->create();

        $response = $this->actingAs($this->user)->get(route('products.index'));

        $response->assertStatus(200);
        foreach ($products as $product) {
            $response->assertSee($product->part_number);
            $response->assertSee($product->name);
        }
    }

    /**
     * Test index can filter by category
     */
    public function test_index_filters_by_category(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $otherProduct = Product::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('products.index', ['category_id' => $category->id]));

        $response->assertStatus(200);
        $response->assertSee($product->part_number);
        $response->assertDontSee($otherProduct->part_number);
    }

    /**
     * Test index can filter by supplier
     */
    public function test_index_filters_by_supplier(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $otherProduct = Product::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('products.index', ['supplier_id' => $supplier->id]));

        $response->assertStatus(200);
        $response->assertSee($product->part_number);
        $response->assertDontSee($otherProduct->part_number);
    }

    /**
     * Test create shows the form with dropdown data
     */
    public function test_create_shows_form(): void
    {
        $response = $this->actingAs($this->user)->get(route('products.create'));

        $response->assertStatus(200);
    }

    /**
     * Test store creates a new product
     */
    public function test_store_creates_product(): void
    {
        $vehicleModel = VehicleModel::factory()->create();
        $supplier = Supplier::factory()->create();
        $category = ProductCategory::factory()->create();

        $data = [
            'part_number' => 'P5188-0KA03',
            'name' => 'Grade Emblem (VRZ)',
            'vehicle_model_id' => $vehicleModel->id,
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'unit' => 'pcs',
            'base_price' => 150000,
            'description' => 'Original Toyota part',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)->post(route('products.store'), $data);

        $this->assertDatabaseHas('products', [
            'part_number' => 'P5188-0KA03',
            'name' => 'Grade Emblem (VRZ)',
        ]);

        $response->assertRedirect(route('products.index'));
    }

    /**
     * Test show displays product details
     */
    public function test_show_displays_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->user)->get(route('products.show', $product));

        $response->assertStatus(200);
        $response->assertSee($product->part_number);
        $response->assertSee($product->name);
    }

    /**
     * Test edit shows the edit form
     */
    public function test_edit_shows_form(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->user)->get(route('products.edit', $product));

        $response->assertStatus(200);
        $response->assertSee($product->part_number);
    }

    /**
     * Test update modifies product
     */
    public function test_update_modifies_product(): void
    {
        $product = Product::factory()->create(['part_number' => 'OLD-001']);
        $vehicleModel = VehicleModel::factory()->create();
        $supplier = Supplier::factory()->create();
        $category = ProductCategory::factory()->create();

        $updateData = [
            'part_number' => 'NEW-002',
            'name' => 'Updated Part Name',
            'vehicle_model_id' => $vehicleModel->id,
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'unit' => 'set',
            'base_price' => 250000,
            'description' => 'Updated description',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)->put(route('products.update', $product), $updateData);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'part_number' => 'NEW-002',
            'name' => 'Updated Part Name',
        ]);

        $response->assertRedirect(route('products.show', $product));
    }

    /**
     * Test destroy deletes product
     */
    public function test_destroy_deletes_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->user)->delete(route('products.destroy', $product));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $response->assertRedirect(route('products.index'));
    }

    /**
     * Test validation for duplicate part_number
     */
    public function test_store_rejects_duplicate_part_number(): void
    {
        $existing = Product::factory()->create(['part_number' => 'DUP-001']);

        $data = [
            'part_number' => 'DUP-001',
            'name' => 'New Product',
            'vehicle_model_id' => VehicleModel::factory()->create()->id,
            'supplier_id' => Supplier::factory()->create()->id,
            'category_id' => ProductCategory::factory()->create()->id,
            'unit' => 'pcs',
        ];

        $response = $this->actingAs($this->user)->post(route('products.store'), $data);

        $response->assertSessionHasErrors('part_number');
        $this->assertDatabaseCount('products', 1);
    }

    /**
     * Test validation for required fields
     */
    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->post(route('products.store'), []);

        $response->assertSessionHasErrors([
            'part_number', 'name', 'vehicle_model_id',
            'supplier_id', 'category_id', 'unit',
        ]);
    }
}
```

- [ ] **Step 2: Run tests**

```bash
php artisan test tests/Feature/ProductControllerTest.php 2>&1
```

Expected: All 11 tests PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/ProductControllerTest.php
git commit -m "feat: add comprehensive ProductController tests"
```

---

## Task 20: Run Migrations & Full Test Suite

**Files:**
- No files created (execution only)

- [ ] **Step 1: Run migrations**

```bash
php artisan migrate 2>&1
```

Expected: Three new tables migrated (or "Nothing to migrate" if already ran).

- [ ] **Step 2: Verify migration status**

```bash
php artisan migrate:status 2>&1 | grep -E "vehicle_models|product_categories|products"
```

Expected: All three show `[N] Ran`.

- [ ] **Step 3: Run full test suite**

```bash
php artisan test 2>&1
```

Expected: Supplier tests + Product tests all pass. Pre-existing auth/import failures may still be present but no NEW failures introduced.

- [ ] **Step 4: Commit (if any migration changes)**

If new migrations ran:
```bash
git add database/migrations/
git commit -m "chore: run product master migrations"
```

---

## Task 21: Create ProductSeeder

**Files:**
- Create: `database/seeders/ProductSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Create seeder file**

Run:
```bash
php artisan make:seeder ProductSeeder
```

- [ ] **Step 2: Replace seeder content**

Edit `database/seeders/ProductSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\VehicleModel;
use App\Models\ProductCategory;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Seed vehicle models if empty
        if (VehicleModel::count() === 0) {
            $models = [
                ['name' => 'Fortuner VRZ', 'brand' => 'Toyota'],
                ['name' => 'Avanza G', 'brand' => 'Toyota'],
                ['name' => 'Innova Zenix', 'brand' => 'Toyota'],
                ['name' => 'CR-V Turbo', 'brand' => 'Honda'],
                ['name' => 'Xpander Cross', 'brand' => 'Mitsubishi'],
            ];
            foreach ($models as $model) {
                VehicleModel::create($model);
            }
        }

        // Seed categories if empty
        if (ProductCategory::count() === 0) {
            $categories = [
                ['name' => 'Body Parts', 'description' => 'Exterior body components'],
                ['name' => 'Engine Components', 'description' => 'Engine and related parts'],
                ['name' => 'Electrical', 'description' => 'Electrical and electronic parts'],
                ['name' => 'Interior', 'description' => 'Interior trim and accessories'],
                ['name' => 'Suspension & Brakes', 'description' => 'Suspension and braking systems'],
            ];
            foreach ($categories as $cat) {
                ProductCategory::create($cat);
            }
        }

        // Seed sample products
        Product::factory(20)->create();
    }
}
```

- [ ] **Step 3: Register in DatabaseSeeder**

Edit `database/seeders/DatabaseSeeder.php`. Add to the `$this->call()` array:

```php
$this->call([
    SupplierSeeder::class,
    ProductSeeder::class,
]);
```

- [ ] **Step 4: Run seeder**

```bash
php artisan db:seed --class=ProductSeeder 2>&1
```

Expected: `INFO Seeding database.` — no errors.

- [ ] **Step 5: Verify data**

```bash
php artisan tinker --execute="echo 'Vehicle Models: ' . \App\Models\VehicleModel::count() . PHP_EOL; echo 'Categories: ' . \App\Models\ProductCategory::count() . PHP_EOL; echo 'Products: ' . \App\Models\Product::count() . PHP_EOL;" 2>&1
```

Expected: Vehicle Models: 5, Categories: 5, Products: 20

- [ ] **Step 6: Commit**

```bash
git add database/seeders/ProductSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat: add ProductSeeder with vehicle models and categories"
```

---

## Summary

| Task | Component | Files | Type |
|------|-----------|-------|------|
| 1 | VehicleModels Migration | 1 | Create |
| 2 | ProductCategories Migration | 1 | Create |
| 3 | Products Migration | 1 | Create |
| 4 | VehicleModel Model | 1 | Create |
| 5 | ProductCategory Model | 1 | Create |
| 6 | Product Model | 1 | Create |
| 7 | Supplier Model (update) | 1 | Modify |
| 8 | VehicleModelFactory | 1 | Create |
| 9 | ProductCategoryFactory | 1 | Create |
| 10 | ProductFactory | 1 | Create |
| 11 | Move Supplier Pages | 4 | Move |
| 12 | SupplierController Update | 1 | Modify |
| 13 | ProductController | 1 | Create |
| 14 | Routes | 1 | Modify |
| 15 | Product Index Page | 1 | Create |
| 16 | Product Create Page | 1 | Create |
| 17 | Product Edit Page | 1 | Create |
| 18 | Product Show Page | 1 | Create |
| 19 | ProductControllerTest | 1 | Create |
| 20 | Run & Verify | 0 | Execute |
| 21 | ProductSeeder | 2 | Create + Modify |
| **Total** | | **23 files** | |

### After Implementation

- Add "Master Data" menu group with Suppliers and Products sub-menus via Menu Management UI
- Vehicle models and categories can be managed via Tinker or direct DB insert (no separate UI in Phase 2)
- Ready for Phase 3: Inventory batches (cycle/rak), multi-supplier pivot, Excel import
