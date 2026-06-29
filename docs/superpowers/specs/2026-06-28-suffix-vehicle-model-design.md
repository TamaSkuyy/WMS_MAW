# Suffix on Vehicle Model & Product Filter on Shipment

**Date:** 2026-06-28  
**Status:** Approved

---

## Background

Parts/products can differ for the same car model depending on the variant (suffix). For example, Toyota Fortuner has variants VRZ, SRZ, TRD, GR Sport — each may use different parts. Currently `vehicle_models` only stores `name` and `brand`, making it impossible to distinguish between variants at the data level.

Additionally, the Shipment create/edit page shows a flat list of all products with no way to filter by vehicle. Users must scroll through all products to find the ones relevant to a specific car being serviced.

---

## Goals

1. Add `suffix` field to `vehicle_models` to distinguish vehicle variants (e.g., Fortuner VRZ vs Fortuner SRZ).
2. Update all product-facing UI to display the suffix when showing vehicle model info.
3. Add a vehicle model filter to Shipment create/edit so selecting a model (with suffix) auto-filters the product list.

---

## Data Model

### `vehicle_models` table changes

| Column | Type | Notes |
|--------|------|-------|
| `suffix` | `string(50)`, nullable | e.g., "VRZ", "TRD", "GR Sport". Nullable because some models have no variant. |

- Unique constraint changes from `(name, brand)` → `(name, brand, suffix)` where suffix treats NULL as distinct.
- No changes to `products` table — products already link via `vehicle_model_id`, which now implicitly carries the suffix.

### Display format

Wherever a vehicle model is shown, format as: `{brand} {name} {suffix}` — e.g., **Toyota Fortuner VRZ**. Suffix omitted if null.

---

## Affected Modules

### 1. VehicleModel (Master Data)

- **Migration:** `add_suffix_to_vehicle_models_table` — adds nullable `suffix string(50)`, drops old unique `(name, brand)`, adds new unique `(name, brand, suffix)`.
- **Model:** add `suffix` to `$fillable`.
- **Controller (store/update):** add `suffix` to validation (`nullable|string|max:50`). Update unique rule on update to include suffix.
- **Index page:** add Suffix column to table.
- **Create/Edit pages:** add optional Suffix input field.

### 2. Products (Master Data)

- No schema change.
- **ProductController (create/edit):** vehicle model label in dropdown already constructed on frontend — no backend change needed.
- **Frontend (Create, Edit, Index, Show):** update vehicle model display to include suffix: `${m.brand} ${m.name}${m.suffix ? ' ' + m.suffix : ''}`.

### 3. Shipments (Transactions)

- No schema change.
- **ShipmentController (create/edit):** pass `vehicleModels` collection to the view (in addition to existing `products` and `racks`).
- **Frontend (Create, Edit):** add a "Filter Model Kendaraan" select above the product picker. Selecting a vehicle model client-side filters the products list to only those with matching `vehicle_model_id`. Selecting blank/all shows all products. This is purely client-side filtering — no additional API call needed.

---

## Constraints & Decisions

- **Suffix nullable:** not all vehicle models have named variants. Existing data is unaffected.
- **Client-side filtering on Shipment:** all active products are already loaded on the Shipment create/edit page. Client-side filtering avoids a new API endpoint and keeps the existing interaction model intact.
- **No suffix field on `products`:** redundant — the vehicle model relationship already carries the suffix.
- **Unique constraint with NULL suffix:** MySQL treats two NULL values as distinct in a unique index, which is the correct behavior here (two records with same name/brand but both NULL suffix would be a data error caught at application level via validation).

---

## Implementation Order

1. Migration: add suffix to vehicle_models
2. VehicleModel model + VehicleModelController
3. VehicleModel frontend (Index, Create, Edit)
4. Product frontend (Create, Edit, Index, Show) — update vehicle model labels
5. ShipmentController — pass vehicleModels
6. Shipment frontend (Create, Edit) — add vehicle model filter
