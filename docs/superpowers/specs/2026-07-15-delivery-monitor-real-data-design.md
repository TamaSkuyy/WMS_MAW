# Warehouse Part Delivery Monitor — Real Data Integration Design Spec

Date: 2026-07-15

## Background

The Delivery Monitor frontend (`resources/js/Pages/DeliveryMonitor/`) was built in a prior phase against a deterministic mock data generator (`utils/mockData.ts`), covering 30 suppliers, ~1650 parts, a fixed 6-slot daily schedule (C1 07:30–09:30 … C6 17:30–19:30), and per-part/per-slot receipts. The UI is confirmed working. This phase replaces the mock data with real backend data.

Investigation of the existing schema found:
- `Product` already fully covers the "Part" concept (part_number, name, category, supplier_id) — no changes needed.
- `Cycle.cycle_number` is a **lifetime**, per-supplier `MAX(cycle_number) + 1` sequence with no date column and no time-of-day concept. It is populated by two independent flows: `CycleController::store()` (manual, free-form `cycle_number`, draft→receive workflow) and `CycleController::quickReceiveStore()` (QR-scan, auto-incremented, immediately `completed`). Neither flow bounds the count to 6/day or ties creation to shift boundaries.
- `CycleItem.quantity` (plan) / `received_quantity` (actual) already match the `PartCycleReceipt.planQty` / `receivedQty` semantics at the row level.
- `Supplier` has no `code` field and no status/health concept anywhere in the codebase.

Confirmed with the user: real cycle creation is **not** reliably bounded to 6/day, so the fixed 6-slot schedule cannot be derived from raw `cycle_number` values. It needs its own master data, while actual received quantities continue to flow through the existing `Cycle`/`CycleItem` tables and their existing create/receive flows (no parallel receiving system).

## Scope

**This phase:** new schema + models for the 6-slot schedule concept, extending `Cycle` with date/slot linkage, computing `Supplier.status` and `ReceiptStatus` on read (no new stored status columns beyond what's listed below), wiring `DeliveryMonitorController` to serve real props, and retiring `mockData.ts` from the live code path.

**Out of scope:** admin UI polish beyond basic CRUD for the two new master tables, real-time push (polling/websocket) — the controller will serve a snapshot per request, matching how the rest of the app currently works; historical/reporting views of past days (the monitor only needs "today").

## Decisions from requirements gathering

| Decision | Choice |
|---|---|
| Supplier→slot assignment | Recurring template (`supplier_delivery_schedules`), not re-entered daily |
| Actual qty data source | Extend existing `Cycle`/`CycleItem` (add `delivery_date`, `delivery_slot_id` to `cycles`) — reuse existing create/QR-scan receive flows as-is |
| Slot time windows (C1 07:30–09:30, etc.) | Real master data table (`delivery_slots`), editable via admin, not hardcoded constants |
| `Supplier.code` | New stored column, auto-generated from name-word initials at creation (collision-safe via numeric suffix), matching the mock generator's existing algorithm |
| `Supplier.status` | Computed on read from schedule + today's cycles — not stored |
| `ReceiptStatus` (matched/shortage/pending/over) | Computed on read from `CycleItem.quantity` vs `received_quantity` — not stored |

## 1. New table: `delivery_slots`

Master data for the 6 fixed daily time windows.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `slot_number` | unsigned tinyint | unique, 1–6 |
| `time_start` | time | e.g. `07:30:00` |
| `time_end` | time | e.g. `09:30:00` |
| `label` | string, nullable | e.g. "Pagi 1", optional display name |
| timestamps | | |

Seeded once with 6 rows matching the current mock windows. Editable later via a standard admin master-data CRUD page (index/create/edit/destroy), following this codebase's existing master-data page conventions (e.g. `VehicleModel`, `ProductCategory`).

`Eloquent`: `DeliverySlot` model, no relations required beyond the inverse ones below.

## 2. New table: `supplier_delivery_schedules`

The recurring template: which suppliers are scheduled in which slots, every day.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `supplier_id` | FK → `suppliers.id`, cascade delete | |
| `delivery_slot_id` | FK → `delivery_slots.id`, cascade delete | |
| timestamps | | |

Unique composite index on (`supplier_id`, `delivery_slot_id`) — a supplier can be scheduled into multiple slots, but not the same slot twice.

`Eloquent`: `SupplierDeliverySchedule` model. `Supplier` gets a new `deliverySchedules(): HasMany` and a `scheduledSlots(): BelongsToMany` (through pivot-like usage) relation to `DeliverySlot`. `DeliverySlot` gets an inverse `scheduledSuppliers(): BelongsToMany` relation to `Supplier`.

Managed via a new small admin page (assign suppliers to slots) — out of scope to fully design here; a simple form (pick supplier, checkbox per slot 1–6) is sufficient and can be scoped at plan time.

## 3. `cycles` table changes

Add two nullable columns:

| Column | Type | Notes |
|---|---|---|
| `delivery_date` | date, nullable | defaults to "today" at creation time in both `store()` and `quickReceiveStore()` |
| `delivery_slot_id` | FK → `delivery_slots.id`, nullable, set null on delete | auto-detected from current time via `DeliverySlot` lookup at creation time in both flows; either flow may allow manual override later, but that UI is out of scope this phase |

Nullable because historical/manual cycles created before this change (and any future edge-case cycle not tied to a slot) must remain valid. `CycleController::store()` and `quickReceiveStore()` both get a small addition: resolve "today's matching `DeliverySlot`" from the current wall-clock time (same interval logic as the mock's `getCurrentCycleNumber`, reimplemented server-side) and set both new columns when creating a `Cycle`. No change to `CycleItem` — its existing `quantity`/`received_quantity` columns are the plan/actual source used by the monitor.

`Cycle` model: add `delivery_date` (date cast) and `deliverySlot(): BelongsTo` relation.

## 4. `suppliers` table changes

Add one column:

| Column | Type | Notes |
|---|---|---|
| `code` | string, unique | auto-generated at creation |

Generation algorithm (ported from `mockData.ts`'s `buildSupplierName`): take the first letter of each significant word in the supplier name (uppercase), forming a 2–3 letter code; on collision with an existing code, append a numeric suffix. Implemented as a private helper in `SupplierController` (or a model `creating` event — decided at plan time) so it runs automatically whenever a supplier is created. Migration adds the column nullable, a one-off backfill step runs the same algorithm once for existing rows, then a follow-up migration step tightens the column to NOT NULL — this order avoids a constraint violation on existing data mid-migration.

## 5. Computed values (no new columns)

### `Supplier.status` (`live | alert | done | standby`)

Computed per request by comparing a supplier's `supplier_delivery_schedules` (template) against its `cycles` where `delivery_date = today`:

- **standby** — no `DeliverySlot`s scheduled for this supplier at all.
- **done** — every scheduled slot for today has a `Cycle` whose total `received_quantity` (summed across its `CycleItem`s) ≥ total `quantity` (plan).
- **alert** — at least one scheduled slot whose `time_end` has already passed (relative to now) but is not yet matched/complete (no `Cycle` for that slot, or received < plan).
- **live** — everything else (a scheduled slot is currently in-progress or upcoming, not yet in shortfall).

### `ReceiptStatus` (`matched | shortage | pending | over`) — per Part per slot

Computed from a `CycleItem` (via its parent `Cycle`'s `delivery_slot_id`, joined by `product_id` = Part):
- no `CycleItem` for that part+slot → **pending**
- `received_quantity == 0` and `quantity > 0` → **pending**
- `received_quantity < quantity` → **shortage**
- `received_quantity == quantity` → **matched**
- `received_quantity > quantity` → **over**

This mirrors the mock generator's status buckets exactly, just computed from real rows instead of random rolls.

## 6. `DeliveryMonitorController`

`index()` gathers, for `delivery_date = today` (or a date passed as a query param, matching the frontend's existing date-picker prop):
- all `Supplier`s with computed `code`, `status`
- all `DeliverySlot`s (the 6 windows, replacing `CYCLE_WINDOWS`)
- all `SupplierDeliverySchedule` rows → shaped into the frontend's `DeliveryCycle[]` (supplierId, cycleNumber = slot_number, timeStart, timeEnd, planQty/actualQty summed from matching `Cycle`+`CycleItem` rows)
- all `Product`s belonging to scheduled suppliers → shaped into `Part[]`
- all `CycleItem`s for today's cycles → shaped into `PartCycleReceipt[]` with computed `status`

Passed as Inertia props matching the existing `types.ts` shapes exactly, so `Index.tsx` and all child components require no changes beyond swapping `generateMockData()` for `usePage().props`. `getCurrentCycleNumber(now)` in the frontend is re-derived from the real `DeliverySlot` list (passed as props) instead of the hardcoded `CYCLE_WINDOWS` array; `mockData.ts` is deleted once this wiring is verified working.

## 7. Migration/seed order

1. `create_delivery_slots_table` + seed the 6 fixed windows (matches current mock values, editable afterward)
2. `create_supplier_delivery_schedules_table`
3. `add_delivery_date_and_slot_to_cycles_table`
4. `add_code_to_suppliers_table` + a one-off backfill (console command or migration-time data step) generating codes for existing suppliers
