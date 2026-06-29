# Default Rack on Product & Smart Pre-fill in Receiving

**Date:** 2026-06-29
**Status:** Approved

---

## Background

Currently, when receiving goods (Cycles → "Terima Barang"), the rack field is always empty and the user must manually select a rack for every product on every receiving. There is no memory of where a product was previously stored, making repeated receivings tedious.

---

## Goals

1. Add an explicit `default_rack_id` to the `products` table so users can declare a preferred rack per product.
2. Record `rack_id` in `cycle_items` when receiving, so history is preserved.
3. Pre-fill the rack field in the Receiving form using a priority cascade: explicit default → last-used history → empty.
4. Show a visual badge ("Default" / "Terakhir dipakai") on pre-filled racks so users know it's auto-filled and can override.

---

## Data Model

### `products` table changes

| Column | Type | Notes |
|--------|------|-------|
| `default_rack_id` | `unsignedBigInteger`, nullable, FK → `racks.id` | Explicit user-set default. SET NULL on rack delete. |

### `cycle_items` table changes

| Column | Type | Notes |
|--------|------|-------|
| `rack_id` | `unsignedBigInteger`, nullable, FK → `racks.id` | Saved when `receive()` is called. Null until received. SET NULL on rack delete. |

---

## Pre-fill Logic

When the Receiving form is opened (`isReceiving = true`), each item's initial `rack_id` is determined by:

```
1. product.default_rack_id       → badge "Default"
2. last cycle_item.rack_id       → badge "Terakhir dipakai"
   (most recent received cycle_item for this product, excluding current cycle)
3. ''                            → no badge, user must select
```

Priority: explicit default wins over history. User can always override.

---

## Affected Modules

### 1. Migrations

- `add_default_rack_id_to_products_table` — nullable FK to `racks`, SET NULL on delete.
- `add_rack_id_to_cycle_items_table` — nullable FK to `racks`, SET NULL on delete.

### 2. Product model + controller + frontend

- **`Product` model:** add `default_rack_id` to `$fillable`, add `defaultRack(): BelongsTo` relation.
- **`ProductController`:** add `default_rack_id` to validation (`nullable|exists:racks,id`). Pass `racks` to `create()` and `edit()` views.
- **`Products/Create.tsx`, `Edit.tsx`:** add optional "Rack Default" SearchableSelect dropdown.
- **`Products/Show.tsx`:** display the default rack name if set.

### 3. CycleController

- **`show()`:** extend eager load to include `items.product.defaultRack`. Also compute `lastUsedRacks` — a map of `product_id → rack_id` from the most recent prior `cycle_items` with a `rack_id` set (excluding the current cycle). Pass both to the view.
- **`receive()`:** save `rack_id` back to `CycleItem` record after validating and updating stock.

### 4. Cycles/Show.tsx (Receiving form)

- Initialize each item's `rack_id` using the priority cascade described above.
- Derive `rackSource` per item: `'default'`, `'history'`, or `'none'`.
- Show small badge next to the rack select: `Default` (blue) or `Terakhir dipakai` (gray). No badge if empty.
- User can change rack freely — badge updates are not needed after user interaction.

---

## Constraints & Decisions

- **SET NULL on rack delete:** if a rack is deleted, `default_rack_id` and `cycle_item.rack_id` become null rather than blocking deletion. Prevents orphan issues.
- **History scope:** only `cycle_items` from other (completed) cycles are used for history. The current cycle's own items are excluded.
- **History lookup is batched:** a single query fetches last rack per product for all items in the cycle, not N queries.
- **Override is always allowed:** the rack field remains editable regardless of pre-fill source.
- **Badge is cosmetic only:** it indicates auto-fill origin but has no functional effect on submission.

---

## Implementation Order

1. Migrations (products + cycle_items)
2. Product model + factory update
3. ProductController (validation + pass racks)
4. Product frontend (Create, Edit, Show)
5. CycleController — `show()` + `receive()`
6. Cycles/Show.tsx — pre-fill + badge
7. Tests
