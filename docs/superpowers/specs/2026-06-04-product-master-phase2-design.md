---
name: Product Master + Reorganization Phase 2
description: Product master data with vehicle models, categories, and folder restructure into Master Data group
type: specification
date: 2026-06-04
scope: Phase 2 — Product master tables, supplier reorganization, Master Data grouping
depends_on: 2026-06-04-supplier-module-design (Phase 1 — suppliers & supplier_addresses)
---

# Product Master — Phase 2 Design

## Overview

Phase 2 extends the Supplier module (Phase 1) with Product master data and reorganizes all master data under a unified "Master Data" navigation group. Three new tables: `products`, `vehicle_models`, and `product_categories`.

**Data source:** Excel spreadsheet with columns — Part Number, Part Name (Update 2025), Model, Supplier.

---

## Database Schema

### New Table 1: VEHICLE_MODELS

```sql
CREATE TABLE vehicle_models (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    brand VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_model_brand (name, brand)
);
```

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| `id` | BIGINT | PK, AUTO_INCREMENT | Unique identifier |
| `name` | VARCHAR(100) | NOT NULL | Model name (e.g., "Fortuner VRZ") |
| `brand` | VARCHAR(100) | NOT NULL | Brand name (e.g., "Toyota") |
| `created_at` | TIMESTAMP | DEFAULT NOW | Record creation |
| `updated_at` | TIMESTAMP | DEFAULT NOW | Last update |

**Unique constraint** on (`name`, `brand`) prevents duplicate model entries.

---

### New Table 2: PRODUCT_CATEGORIES

```sql
CREATE TABLE product_categories (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULLABLE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| `id` | BIGINT | PK, AUTO_INCREMENT | Unique identifier |
| `name` | VARCHAR(100) | NOT NULL, UNIQUE | Category name |
| `description` | TEXT | NULLABLE | Optional description |
| `created_at` | TIMESTAMP | DEFAULT NOW | Record creation |
| `updated_at` | TIMESTAMP | DEFAULT NOW | Last update |

---

### New Table 3: PRODUCTS

```sql
CREATE TABLE products (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    part_number VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    vehicle_model_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
    description TEXT NULLABLE,
    base_price DECIMAL(12,2) NULLABLE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY fk_product_model (vehicle_model_id) REFERENCES vehicle_models(id) ON DELETE RESTRICT,
    FOREIGN KEY fk_product_supplier (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY fk_product_category (category_id) REFERENCES product_categories(id) ON DELETE RESTRICT,
    INDEX idx_part_number (part_number),
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_category_id (category_id),
    INDEX idx_vehicle_model_id (vehicle_model_id)
);
```

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| `id` | BIGINT | PK, AUTO_INCREMENT | Unique identifier |
| `part_number` | VARCHAR(50) | NOT NULL, UNIQUE | SKU/Part code from Excel |
| `name` | VARCHAR(255) | NOT NULL | Part name |
| `vehicle_model_id` | BIGINT | FK, NOT NULL | Reference to vehicle_models |
| `supplier_id` | BIGINT | FK, NOT NULL | Reference to suppliers |
| `category_id` | BIGINT | FK, NOT NULL | Reference to product_categories |
| `unit` | VARCHAR(20) | NOT NULL, DEFAULT 'pcs' | Unit of measure |
| `description` | TEXT | NULLABLE | Additional notes |
| `base_price` | DECIMAL(12,2) | NULLABLE | Standard purchase price |
| `is_active` | BOOLEAN | NOT NULL, DEFAULT TRUE | Soft enable/disable |
| `created_at` | TIMESTAMP | DEFAULT NOW | Record creation |
| `updated_at` | TIMESTAMP | DEFAULT NOW | Last update |

**Foreign keys** use RESTRICT (not CASCADE) — products cannot be deleted if referenced, preventing accidental data loss.

**Design decision:** 1 Product → 1 Supplier. If multi-supplier needed later, add a pivot table `product_supplier` without breaking existing FK.

---

### Validation Rules

```php
// vehicle_models
'name' => 'required|string|max:100',
'brand' => 'required|string|max:100',

// product_categories
'name' => 'required|string|max:100|unique:product_categories',
'description' => 'nullable|string|max:500',

// products
'part_number' => 'required|string|max:50|unique:products',
'name' => 'required|string|max:255',
'vehicle_model_id' => 'required|exists:vehicle_models,id',
'supplier_id' => 'required|exists:suppliers,id',
'category_id' => 'required|exists:product_categories,id',
'unit' => 'required|string|max:20',
'description' => 'nullable|string|max:1000',
'base_price' => 'nullable|numeric|min:0',
'is_active' => 'boolean',
```

---

## Models & Relationships

### VehicleModel
```php
class VehicleModel extends Model
{
    protected $fillable = ['name', 'brand'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
```

### ProductCategory
```php
class ProductCategory extends Model
{
    protected $fillable = ['name', 'description'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
```

### Product
```php
class Product extends Model
{
    protected $fillable = [
        'part_number', 'name', 'vehicle_model_id',
        'supplier_id', 'category_id', 'unit',
        'description', 'base_price', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'base_price' => 'decimal:2',
    ];

    public function vehicleModel(): BelongsTo { ... }
    public function supplier(): BelongsTo { ... }
    public function category(): BelongsTo { ... }
}
```

### Supplier (update existing)
```php
// Add to existing Supplier model:
public function products(): HasMany
{
    return $this->hasMany(Product::class);
}
```

---

## Folder Reorganization

### Frontend Pages — Move & Create

```
resources/js/Pages/
└── Master/                          ← NEW folder
    ├── Suppliers/                   ← MOVED from Pages/Suppliers/
    │   ├── Index.tsx
    │   ├── Create.tsx
    │   ├── Edit.tsx
    │   └── Show.tsx
    │
    └── Products/                    ← NEW
        ├── Index.tsx                ← Table list with search & filter
        ├── Create.tsx               ← Form with dropdowns for model/category/supplier
        ├── Edit.tsx                 ← Edit form
        └── Show.tsx                 ← Detail view with supplier info
```

### Controller `Inertia::render` updates

All `SupplierController` references change:
- `Suppliers/Index` → `Master/Suppliers/Index`
- `Suppliers/Create` → `Master/Suppliers/Create`
- `Suppliers/Show` → `Master/Suppliers/Show`
- `Suppliers/Edit` → `Master/Suppliers/Edit`

### Routes

```php
// web.php — Master Data group
Route::middleware(['auth'])->group(function () {
    Route::resource('suppliers', SupplierController::class);
    Route::resource('products', ProductController::class);
});
```

### Sidebar Navigation

```
📊 Dashboard
─────────────────
📁 Master Data
  ├── 📦 Suppliers
  └── 🏷️ Products
─────────────────
📋 Menus
👥 Users
🔒 Roles
🔑 Permissions
```

---

## ProductController (Resource)

| Method | Route | Inertia Page | Props |
|--------|-------|-------------|-------|
| `index` | `GET /products` | `Master/Products/Index` | `products` (paginated with vehicleModel, supplier, category) |
| `create` | `GET /products/create` | `Master/Products/Create` | `vehicleModels`, `categories`, `suppliers` (for dropdowns) |
| `store` | `POST /products` | redirect | — |
| `show` | `GET /products/{product}` | `Master/Products/Show` | `product` (with vehicleModel, supplier, category) |
| `edit` | `GET /products/{product}/edit` | `Master/Products/Edit` | `product`, `vehicleModels`, `categories`, `suppliers` |
| `update` | `PUT /products/{product}` | redirect | — |
| `destroy` | `DELETE /products/{product}` | redirect | — |

### Vehicle Models & Categories Management

No separate CRUD pages. Managed inline:
- **Create form:** "Add new model" / "Add new category" buttons open a small modal or inline input
- **Alternative:** Admin manages via Tinker or simple seeders (Phase 2 scope is minimal)

---

## Files Summary

| # | Action | File |
|---|--------|------|
|  | **Migrations** | |
| 1 | Create | `database/migrations/..._create_vehicle_models_table.php` |
| 2 | Create | `database/migrations/..._create_product_categories_table.php` |
| 3 | Create | `database/migrations/..._create_products_table.php` |
|  | **Models** | |
| 4 | Create | `app/Models/VehicleModel.php` |
| 5 | Create | `app/Models/ProductCategory.php` |
| 6 | Create | `app/Models/Product.php` |
| 7 | Update | `app/Models/Supplier.php` (add products relationship) |
|  | **Factories** | |
| 8 | Create | `database/factories/VehicleModelFactory.php` |
| 9 | Create | `database/factories/ProductCategoryFactory.php` |
| 10 | Create | `database/factories/ProductFactory.php` |
|  | **Controllers** | |
| 11 | Create | `app/Http/Controllers/ProductController.php` |
| 12 | Update | `app/Http/Controllers/SupplierController.php` (update render paths) |
|  | **Routes** | |
| 13 | Update | `routes/web.php` (add product resource route) |
|  | **Frontend** (Inertia React) | |
| 14-17 | Move | `resources/js/Pages/Suppliers/*` → `resources/js/Pages/Master/Suppliers/*` |
| 18-21 | Create | `resources/js/Pages/Master/Products/{Index,Create,Edit,Show}.tsx` |
| 22 | Update | `resources/js/Tailadmin/layout/AppSidebar.tsx` (add Master Data group) |
|  | **Tests** | |
| 23 | Create | `tests/Feature/ProductControllerTest.php` |
| 24 | Update | `tests/Feature/SupplierControllerTest.php` (update render path assertions) |
|  | **Seeders** | |
| 25 | Create | `database/seeders/ProductSeeder.php` |

---

## Testing Strategy

### ProductControllerTest
- Index displays products with filters (by supplier, category, model)
- Create shows form with dropdown options
- Store creates product with valid data
- Store validates required fields & unique part_number
- Show displays product details
- Edit shows form pre-filled
- Update modifies product
- Destroy deletes product (RESTRICT — fails if referenced elsewhere) (note: current scope has no references)
- Filter by category/supplier/model
- Toggle is_active status

### Updated SupplierControllerTest
- Update page path assertions from `Suppliers/*` to `Master/Suppliers/*`

---

## Phase 3 (Future)

| Feature | Description |
|---------|-------------|
| Inventory batches | "Cycle" for receiving goods, rack storage |
| Multi-supplier | Pivot table for products with multiple suppliers |
| Pricing tiers | Bulk discounts, minimum order quantities |
| Lead times | Per-supplier delivery estimates |
| Import from Excel | Direct Excel upload to populate products |
