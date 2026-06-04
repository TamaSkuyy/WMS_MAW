---
name: Cycle, Rack & Stock — Phase 3 Transaction Module
description: Receiving goods per cycle from suppliers, rack storage, and inventory tracking
type: specification
date: 2026-06-04
scope: Phase 3 — Delivery cycles (transaksi penerimaan), rack locations, stock/inventory
depends_on:
  - 2026-06-04-supplier-module-design (Phase 1 — suppliers)
  - 2026-06-04-product-master-phase2-design (Phase 2 — products)
---

# Cycle, Rack & Stock — Phase 3

## Overview

Phase 3 adds the operational layer: receiving goods from suppliers via delivery cycles, storing them in racks, and tracking inventory. Bridges master data (suppliers, products) with real-world warehouse operations.

**Data source:** Excel delivery documents — per supplier, per cycle, with part numbers and quantities.

---

## Database Schema

### New Table 1: RACKS (Master)

```sql
CREATE TABLE racks (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL UNIQUE,
    zone VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| `id` | BIGINT | PK | Unique identifier |
| `code` | VARCHAR(20) | NOT NULL, UNIQUE | e.g., "A-01", "B-03" |
| `zone` | VARCHAR(50) | NOT NULL | e.g., "Zona A", "Zona B" |

---

### New Table 2: CYCLES (Transaction Header)

```sql
CREATE TABLE cycles (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    supplier_id BIGINT UNSIGNED NOT NULL,
    cycle_number INT UNSIGNED NOT NULL,
    status ENUM('draft','receiving','completed') NOT NULL DEFAULT 'draft',
    received_at TIMESTAMP NULLABLE,
    notes TEXT NULLABLE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY fk_cycle_supplier (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_supplier_cycle (supplier_id, cycle_number),
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_status (status)
);
```

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| `id` | BIGINT | PK | Unique identifier |
| `supplier_id` | BIGINT | FK, NOT NULL | Supplier who sent this cycle |
| `cycle_number` | INT | NOT NULL | e.g., 1, 2, 3 per supplier |
| `status` | ENUM | draft / receiving / completed | Lifecycle state |
| `received_at` | TIMESTAMP | NULLABLE | When receiving was completed |
| `notes` | TEXT | NULLABLE | Additional notes |

**Unique:** (`supplier_id`, `cycle_number`) — Cycle numbers are per supplier.

**Status flow:** `draft` → `receiving` → `completed`

---

### New Table 3: CYCLE_ITEMS (Transaction Detail)

```sql
CREATE TABLE cycle_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    cycle_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    received_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    notes TEXT NULLABLE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY fk_item_cycle (cycle_id) REFERENCES cycles(id) ON DELETE CASCADE,
    FOREIGN KEY fk_item_product (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_cycle_product (cycle_id, product_id),
    INDEX idx_cycle_id (cycle_id),
    INDEX idx_product_id (product_id)
);
```

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| `id` | BIGINT | PK | Unique identifier |
| `cycle_id` | BIGINT | FK, NOT NULL | Parent cycle |
| `product_id` | BIGINT | FK, NOT NULL | Product being delivered |
| `quantity` | INT | NOT NULL, DEFAULT 0 | Quantity from delivery document |
| `received_quantity` | INT | NOT NULL, DEFAULT 0 | Actually received (after verification) |
| `notes` | TEXT | NULLABLE | e.g., "2 pcs rusak" |

**Unique:** (`cycle_id`, `product_id`) — One product appears once per cycle.

**Cascade delete:** If cycle deleted, items deleted too.

---

### New Table 4: STOCKS (Inventory)

```sql
CREATE TABLE stocks (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    rack_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY fk_stock_product (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY fk_stock_rack (rack_id) REFERENCES racks(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_product_rack (product_id, rack_id),
    INDEX idx_product_id (product_id),
    INDEX idx_rack_id (rack_id)
);
```

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| `id` | BIGINT | PK | Unique identifier |
| `product_id` | BIGINT | FK, NOT NULL | Product |
| `rack_id` | BIGINT | FK, NOT NULL | Rack location |
| `quantity` | INT | NOT NULL, DEFAULT 0 | Current stock level |

**Unique:** (`product_id`, `rack_id`) — One stock record per product per rack.

---

## Business Flow

```
1. DRAFT
   User creates cycle → selects supplier → adds items (product + qty)
   
2. RECEIVING
   Goods arrive → user opens cycle → changes status to "receiving"
   → inputs received_quantity per item (can differ from document qty)
   
3. COMPLETED
   Receiving done → status changed to "completed"
   → stock automatically added: stocks.quantity += received_quantity
   → received_at timestamp set
```

**Stock placement:** When completing a cycle, user assigns each item to a rack. Stock is incremented per (product, rack) pair.

---

## Models & Relationships

### Rack
```php
class Rack extends Model
{
    protected $fillable = ['code', 'zone'];
    public function stocks(): HasMany { return $this->hasMany(Stock::class); }
}
```

### Cycle
```php
class Cycle extends Model
{
    protected $fillable = ['supplier_id', 'cycle_number', 'status', 'received_at', 'notes'];
    protected $casts = ['received_at' => 'datetime'];
    
    public function supplier(): BelongsTo { ... }
    public function items(): HasMany { return $this->hasMany(CycleItem::class); }
}
```

### CycleItem
```php
class CycleItem extends Model
{
    protected $fillable = ['cycle_id', 'product_id', 'quantity', 'received_quantity', 'notes'];
    
    public function cycle(): BelongsTo { ... }
    public function product(): BelongsTo { ... }
}
```

### Stock
```php
class Stock extends Model
{
    protected $fillable = ['product_id', 'rack_id', 'quantity'];
    
    public function product(): BelongsTo { ... }
    public function rack(): BelongsTo { ... }
}
```

### Supplier (update)
```php
// Add to existing:
public function cycles(): HasMany { return $this->hasMany(Cycle::class); }
```

### Product (update)
```php
// Add to existing:
public function cycleItems(): HasMany { return $this->hasMany(CycleItem::class); }
public function stocks(): HasMany { return $this->hasMany(Stock::class); }
```

---

## Validation Rules

```php
// racks
'code' => 'required|string|max:20|unique:racks',
'zone' => 'required|string|max:50',

// cycles
'supplier_id' => 'required|exists:suppliers,id',
'cycle_number' => 'required|integer|min:1',
'status' => 'required|in:draft,receiving,completed',
'notes' => 'nullable|string|max:500',

// cycle_items
'product_id' => 'required|exists:products,id',
'quantity' => 'required|integer|min:1',
'received_quantity' => 'nullable|integer|min:0',
'notes' => 'nullable|string|max:200',

// stocks
'product_id' => 'required|exists:products,id',
'rack_id' => 'required|exists:racks,id',
'quantity' => 'required|integer|min:0',
```

---

## Controllers

### RackController & CycleController (Resource)
Standard resource controllers with Inertia rendering.

### Key Business Logic (CycleController)

**complete(Cycle $cycle):**
- Validate all items have received_quantity >= 0
- For each item: find or create Stock record for (product_id, rack_id), increment quantity
- Set cycle.status = 'completed', cycle.received_at = now()

---

## Frontend Pages

### Racks
- `Master/Racks/Index.tsx` — table list
- `Master/Racks/Create.tsx` — simple code + zone form
- `Master/Racks/Edit.tsx`

### Cycles
- `Transactions/Cycles/Index.tsx` — list grouped by supplier
- `Transactions/Cycles/Create.tsx` — select supplier, add items (product search + qty)
- `Transactions/Cycles/Show.tsx` — cycle detail with items + receiving form
- `Transactions/Cycles/Edit.tsx` — edit draft cycle

### Stocks
- `Transactions/Stocks/Index.tsx` — inventory view (filter by product, rack, zone)

---

## Navigation Grouping

```
📊 Dashboard
─────────────────
📁 Master Data
  ├── 📦 Suppliers
  ├── 🏷️ Products
  └── 🗄️ Racks              ← NEW
─────────────────
📁 Transactions              ← NEW group
  ├── 🚚 Cycles              ← NEW
  └── 📊 Stocks              ← NEW
─────────────────
📋 Menus | 👥 Users | 🔒 Roles | 🔑 Permissions
```

---

## Files Summary

| # | Action | File |
|---|--------|------|
| 1-3 | Create | Migrations: racks, cycles, cycle_items, stocks |
| 4-7 | Create | Models: Rack, Cycle, CycleItem, Stock |
| 8-9 | Update | Models: Supplier (add cycles), Product (add cycleItems, stocks) |
| 10-13 | Create | Factories: RackFactory, CycleFactory, CycleItemFactory, StockFactory |
| 14-15 | Create | Controllers: RackController, CycleController |
| 16-17 | Update | Routes: add rack + cycle resources |
| 18-26 | Create | Pages: 3 Rack + 4 Cycle + 2 Stock pages |
| 27-28 | Create | Tests: RackControllerTest, CycleControllerTest |
| 29 | Create | Seeders: RackSeeder, CycleSeeder |
