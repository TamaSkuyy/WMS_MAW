---
name: Supplier Module Database Design
description: Database schema and architecture for the Supplier module in Warehouse Management System (WMS)
type: specification
date: 2026-06-04
scope: Phase 1 - Minimal supplier tracking with basic info and address
next_phase: Add supplier products relationship, pricing tiers, lead times, payment terms, ratings
---

# Supplier Module Database Design

## Overview

The Supplier module is the foundation for tracking product sources in the Warehouse Management System. Phase 1 focuses on minimal viable functionality: basic supplier information and single address storage. The design supports natural growth for future features like pricing, lead times, and supplier ratings.

**Design Philosophy:** Separated concerns (contact vs address) allow flexible expansion without schema disruption.

---

## Phase 1: Minimal Requirements

### Data Requirements

**Suppliers are:**

- Product sources (provide items that enter warehouse inventory)
- Each has one address (single location)
- Tracked with contact info and email
- Future-ready for products relationship

---

## Database Schema

### Table 1: SUPPLIERS

**Purpose:** Store core supplier information and contact details

```sql
CREATE TABLE suppliers (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    contact_person VARCHAR(255) NULLABLE,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20) NULLABLE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_email (email),
    INDEX idx_name (name)
);
```

| Field            | Type         | Constraints        | Notes                     |
| ---------------- | ------------ | ------------------ | ------------------------- |
| `id`             | BIGINT       | PK, AUTO_INCREMENT | Unique identifier         |
| `name`           | VARCHAR(255) | NOT NULL, UNIQUE   | Supplier company name     |
| `contact_person` | VARCHAR(255) | NULLABLE           | Contact person name       |
| `email`          | VARCHAR(255) | NOT NULL, UNIQUE   | Primary contact email     |
| `phone`          | VARCHAR(20)  | NULLABLE           | Phone number              |
| `created_at`     | TIMESTAMP    | DEFAULT NOW        | Record creation timestamp |
| `updated_at`     | TIMESTAMP    | DEFAULT NOW        | Last updated timestamp    |

**Laravel Validation Rules:**

```php
'name' => 'required|string|max:255|unique:suppliers',
'contact_person' => 'nullable|string|max:255',
'email' => 'required|email|unique:suppliers',
'phone' => 'nullable|string|max:20',
```

---

### Table 2: SUPPLIER_ADDRESSES

**Purpose:** Store supplier location/address information. Structured for future multi-address support.

```sql
CREATE TABLE supplier_addresses (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    supplier_id BIGINT UNSIGNED NOT NULL,
    street VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(100) NOT NULL DEFAULT 'Indonesia',
    address_type ENUM('primary', 'shipping', 'billing') DEFAULT 'primary',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY fk_supplier (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_supplier_address_type (supplier_id, address_type),
    INDEX idx_supplier_id (supplier_id)
);
```

| Field          | Type         | Constraints                   | Notes                               |
| -------------- | ------------ | ----------------------------- | ----------------------------------- |
| `id`           | BIGINT       | PK, AUTO_INCREMENT            | Unique identifier                   |
| `supplier_id`  | BIGINT       | FK, NOT NULL                  | Reference to suppliers table        |
| `street`       | VARCHAR(255) | NOT NULL                      | Street address                      |
| `city`         | VARCHAR(100) | NOT NULL                      | City name                           |
| `state`        | VARCHAR(100) | NOT NULL                      | State/Province                      |
| `postal_code`  | VARCHAR(20)  | NOT NULL                      | Postal/ZIP code                     |
| `country`      | VARCHAR(100) | NOT NULL, DEFAULT 'Indonesia' | Country name                        |
| `address_type` | ENUM         | DEFAULT 'primary'             | Type: primary, shipping, or billing |
| `created_at`   | TIMESTAMP    | DEFAULT NOW                   | Record creation timestamp           |
| `updated_at`   | TIMESTAMP    | DEFAULT NOW                   | Last updated timestamp              |

**Constraints:**

- Foreign Key: `supplier_id` → CASCADE DELETE (if supplier deleted, addresses deleted)
- Unique: `(supplier_id, address_type)` — Only one address per type per supplier

**Laravel Validation Rules:**

```php
'street' => 'required|string|max:255',
'city' => 'required|string|max:100',
'state' => 'required|string|max:100',
'postal_code' => 'required|string|max:20',
'country' => 'required|string|max:100',
'address_type' => 'required|in:primary,shipping,billing',
```

---

## Laravel Models

### Model 1: Supplier.php

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    // Relationships
    public function addresses()
    {
        return $this->hasMany(SupplierAddress::class);
    }

    public function primaryAddress()
    {
        return $this->hasOne(SupplierAddress::class)
                    ->where('address_type', 'primary');
    }

    // Future relationship (Phase 2)
    // public function products() { ... }
}
```

### Model 2: SupplierAddress.php

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    // Relationships
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
```

---

## Architecture & Data Flow

```
┌─────────────────┐
│   SUPPLIERS     │
│  (Core Info)    │
│  ├─ id (PK)     │
│  ├─ name        │
│  ├─ email       │
│  ├─ contact     │
│  └─ phone       │
└────────┬────────┘
         │ 1:Many
         │
    ┌────▼──────────────────┐
    │ SUPPLIER_ADDRESSES    │
    │  (Location)           │
    │  ├─ id (PK)           │
    │  ├─ supplier_id (FK)  │
    │  ├─ street            │
    │  ├─ city              │
    │  ├─ state             │
    │  ├─ postal_code       │
    │  ├─ country           │
    │  └─ address_type      │
    └───────────────────────┘

[Future Phase 2]
         │ 1:Many
         ▼
    ┌──────────────┐
    │   PRODUCTS   │
    │ (sku, name)  │
    └──────────────┘
```

---

## Future Extensibility (Phase 2+)

### Planned Additions

| Feature               | Table Impact                          | Reason                                                      |
| --------------------- | ------------------------------------- | ----------------------------------------------------------- |
| Products relationship | Add `supplier_id` to `products` table | Track which supplier provides each product                  |
| Supplier categories   | Add new `supplier_categories` table   | Organize suppliers by type (raw materials, equipment, etc.) |
| Lead times            | Add `default_lead_days` to suppliers  | Expected delivery time                                      |
| Payment terms         | Add `payment_terms` to suppliers      | COD, Net 30, etc.                                           |
| Ratings               | Add new `supplier_ratings` table      | Quality/performance tracking                                |
| Pricing tiers         | Add new `supplier_pricing` table      | Bulk discounts, minimum orders                              |

**Why separated tables?** Each phase can be implemented independently without breaking existing queries or migrations.

---

## Implementation Steps

### Step 1: Create Migrations

```bash
php artisan make:migration create_suppliers_table
php artisan make:migration create_supplier_addresses_table
```

### Step 2: Generate Models

```bash
php artisan make:model Supplier
php artisan make:model SupplierAddress
```

### Step 3: Create Factories (for testing)

```bash
php artisan make:factory SupplierFactory --model=Supplier
php artisan make:factory SupplierAddressFactory --model=SupplierAddress
```

### Step 4: Create Controllers & Routes

```bash
php artisan make:controller SupplierController --model=Supplier --resource
php artisan make:controller SupplierAddressController --model=SupplierAddress --resource
```

### Step 5: Run Migrations

```bash
php artisan migrate
```

---

## Usage Examples (Post-Implementation)

### Retrieve supplier with address

```php
$supplier = Supplier::with('primaryAddress')->find(1);
echo $supplier->name;
echo $supplier->primaryAddress->city;
```

### Create supplier with address

```php
$supplier = Supplier::create([
    'name' => 'PT Maju Jaya',
    'contact_person' => 'Budi Santoso',
    'email' => 'budi@majujaya.com',
    'phone' => '+62812345678',
]);

$supplier->addresses()->create([
    'street' => 'Jl. Merdeka 123',
    'city' => 'Jakarta',
    'state' => 'DKI Jakarta',
    'postal_code' => '12000',
    'country' => 'Indonesia',
    'address_type' => 'primary',
]);
```

---

## Testing Strategy

### Unit Tests

- Model relationships (Supplier → SupplierAddress)
- Model validation rules
- Cascade delete behavior

### Feature Tests

- Create supplier with address
- Update supplier info
- Delete supplier (and verify addresses deleted)
- Query by email, name
- Retrieve supplier with related address

### Database Seed (Optional)

Create sample suppliers for local development testing.

---

## Notes

- **Timestamps:** Both tables include `created_at` and `updated_at` for audit tracking
- **Defaults:** Country defaults to 'Indonesia' — can be changed per region
- **Address Type:** Starting with 'primary' only. Enum supports 'shipping' and 'billing' for Phase 2
- **Unique Constraints:** Email and name must be unique per supplier to prevent duplicates
- **Cascade Delete:** If supplier deleted, all addresses automatically removed (data integrity)

---

## Claude Code Quick Commands

**To open and review this design:**

```bash
# Open spec in editor
code docs/superpowers/specs/2026-06-04-supplier-module-design.md

# View database diagram (when dbdiagram.io format added)
# Link to dbdiagram: [To be created after approval]
```

**To generate dbdiagram.io format from this spec:**

```
Visit: https://dbdiagram.io/
Create new diagram and paste the format below when ready
```

**Example dbdiagram.io DDL:**

```dbdiagram
Table suppliers {
  id bigint [pk, increment]
  name varchar(255) [unique, not null]
  contact_person varchar(255)
  email varchar(255) [unique, not null]
  phone varchar(20)
  created_at timestamp [default: `CURRENT_TIMESTAMP`]
  updated_at timestamp [default: `CURRENT_TIMESTAMP`]
}

Table supplier_addresses {
  id bigint [pk, increment]
  supplier_id bigint [ref: > suppliers.id]
  street varchar(255) [not null]
  city varchar(100) [not null]
  state varchar(100) [not null]
  postal_code varchar(20) [not null]
  country varchar(100) [not null, default: 'Indonesia']
  address_type enum('primary', 'shipping', 'billing') [default: 'primary']
  created_at timestamp
  updated_at timestamp

  indexes {
    (supplier_id, address_type) [unique]
  }
}
```

---

## Approval Status

✅ **Approved by User:** 2026-06-04
**Design Phase:** Phase 1 - Minimal Supplier Tracking
**Next Step:** Implementation Plan (writing-plans skill)
