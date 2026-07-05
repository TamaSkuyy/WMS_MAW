# Transaction Input Protection — Design Spec

Date: 2026-07-05

## Background

The Transactions module has two flows that mutate `Stock`:

- **Cycles** (`CycleController`) — receiving items from a supplier. `store`/`update` create a draft with line items; `receive` (and `quickReceiveStore`) commits received quantities into `Stock`.
- **Shipments** (`ShipmentController`) — sending items to a partner. `store`/`update` create a draft with line items; `ship` deducts quantities from `Stock`.

Three gaps were found in these flows:

1. No dedupe of duplicate line items within a single submitted transaction (e.g. the same product entered twice).
2. `receive()` and `ship()` are not wrapped in a locked DB transaction, so concurrent requests (double-click, two tabs, retried request) can double-apply a stock mutation or race past a stock-availability check (TOCTOU), potentially driving `Stock.quantity` negative.
3. Several forms have no "submitting" guard, so a fast double-click can fire the mutating request twice from the client.

This spec adds backend enforcement (source of truth) plus a matching client-side guard, without changing any database schema.

## Scope

- `app/Http/Controllers/CycleController.php`: `store`, `update`, `receive`, `quickReceiveStore`
- `app/Http/Controllers/ShipmentController.php`: `store`, `update`, `ship`
- `resources/js/Pages/Transactions/Cycles/Create.tsx`
- `resources/js/Pages/Transactions/Cycles/Edit.tsx`
- `resources/js/Pages/Transactions/Cycles/Show.tsx` (receive form)
- `resources/js/Pages/Transactions/Shipments/Show.tsx` (ship button)

Out of scope: `Shipments/Create.tsx` and `Shipments/Edit.tsx` already guard double-submit with a `submitting` state — no change needed. `Stocks` module (view-only, `index` route only) is unaffected. No new migrations.

## 1. Duplicate line-item handling (dedupe + sum)

Before persisting items in `store`/`update`, validated items are grouped and summed so duplicate entries merge instead of creating duplicate rows or being silently dropped/rejected.

- **Cycle** (`CycleController::store`/`update`): no rack is chosen at this stage, so the grouping key is `product_id`. Two lines with the same `product_id` become one line with summed `quantity`.
- **Shipment** (`ShipmentController::store`/`update`): rack is chosen at this stage, so the grouping key is `product_id` + `rack_id`. Same product in a different rack stays a separate line; same product **and** same rack merge with summed `quantity`.

Implementation: a small private helper per controller, e.g. `mergeDuplicateItems(array $items, array $keys): array`, run on `$validated['items']` right after `$request->validate()` and before the create/replace loop. Existing per-item validation rules (`required|exists`, `min:1`, etc.) are unaffected since they run on the raw submitted array first; merging happens after validation passes.

## 2. Race condition protection (stock mutations)

`receive()`, `ship()`, and `quickReceiveStore()` are wrapped in `DB::transaction()` with row locking:

- **`CycleController::receive()`**: lock the `Cycle` row with `lockForUpdate()` inside the transaction and re-check `status` is still `draft`/`receiving` before applying changes — closes the double-click/two-tab race where both requests read the pre-update status. Each `Stock` row touched is fetched/created with `lockForUpdate()` before its `quantity` is incremented and saved.
- **`ShipmentController::ship()`**: lock the `Shipment` row and re-check `status === 'draft'` inside the transaction. Each `Stock` row is locked with `lockForUpdate()` before the availability check and before decrementing — this removes the TOCTOU window between "check quantity is sufficient" and "deduct quantity," which today can be exploited by two concurrent `ship()` calls to push `Stock.quantity` below zero.
- **`CycleController::quickReceiveStore()`**: already wrapped in `DB::transaction`; add `lockForUpdate()` on the `Stock` lookup for consistency with `receive()`.

Behavior on conflict: if a second concurrent request finds the parent row's status already advanced past `draft`/`receiving`, it fails the existing status guard and returns the existing "Cannot receive/ship this" error — no silent double-processing.

## 3. Double-submit protection (frontend)

Applies the `submitting`/`processing` guard pattern already used in `Shipments/Create.tsx` and `Cycles/QuickReceive.tsx`:

- **`Cycles/Create.tsx`**, **`Cycles/Edit.tsx`**: both already use Inertia's `useForm`, which exposes `processing`. Destructure it and add it to the submit button's `disabled` condition (alongside the existing `items.length === 0` check).
- **`Cycles/Show.tsx`** (`handleReceive`): add local `submitting` state (`useState`), set `true` on submit, pass `onFinish: () => setSubmitting(false)` to `router.post`, and disable the submit button while `submitting` is true.
- **`Shipments/Show.tsx`** (`handleShip`): same pattern — local `submitting` state, disable the "Kirim Sekarang" button while the `ship` request is in flight.

This is a client-side UX guard only; the backend transaction/locking in section 2 is the authoritative protection.

## Error handling

- Dedupe/merge is silent (no user-facing error) — it's a normalization step, not a rejection.
- Lock-based conflicts surface through the existing status-guard error responses (`back()->with('error', ...)`), no new error copy needed.
- No changes to existing validation error messages/fields.

## Testing

- Feature tests (Pest/PHPUnit, matching existing `tests/Feature` conventions):
  - Cycle/Shipment `store` merges duplicate items and sums quantity correctly (both dedupe keys).
  - `receive()`/`ship()` reject a second call once status has advanced (simulate by calling twice sequentially — the second call must hit the status guard).
  - Stock quantity ends up correct (no double-add/double-deduct) after a successful call.
- No new frontend automated tests planned; double-submit guard is verified manually (per the project's existing testing pattern for these pages).
