# Warehouse Part Delivery Monitor (TV Dashboard) — Design Spec

Date: 2026-07-14

## Background

A new large-format TV/monitor dashboard for tracking supplier delivery cycles into the warehouse in real time: a grid of supplier status cards, a detail panel for the focused supplier, and a full parts-delivery table with per-cycle plan/actual tracking. Full requirements were provided directly by the user (layout, component breakdown, data model sketch, technical constraints) — this spec adapts that request to this codebase's existing conventions.

Investigation found the existing `/tv-dashboard` (`TvDashboardController` + `resources/js/Pages/TvDashboard/Index.tsx`) is an unrelated, simpler slideshow of per-product stock levels — it does not model suppliers, delivery cycles, or a parts table, and shares no data with this new feature. The existing `Cycle`/`CycleItem` Eloquent models represent a one-off receiving batch (like a PO/GRN, arbitrary `cycle_number`, no time-of-day concept) — fundamentally different from the "6 fixed daily time-slotted delivery cycles per supplier" (C1 07:30–09:30 … C6) this feature needs. New backend tables will be required in a later phase; this phase is frontend-only.

## Scope

**This phase (6 stages, executed one at a time with a confirmation gate between each per the user's explicit request):**
1. Empty component scaffold + routing
2. Mock data generator (30 suppliers, ~1650 parts)
3. Header
4. SupplierGrid + SupplierCard
5. SupplierDetailPanel
6. PartsTable with filters/search (virtualized)

**Out of scope for this phase:** real Eloquent models/migrations (`Supplier` already exists and will be reused later; `DeliveryCycle`/`Part`/`PartCycleReceipt` are new and deferred), backend controller logic beyond a trivial page render, real-time polling wiring (structure will allow it, per the user's tech requirements, but the actual poll/websocket wiring is deferred until there's a real backend to poll).

## Decisions from requirements gathering

| Decision | Choice |
|---|---|
| Route | New, separate: `/delivery-monitor` (existing `/tv-dashboard` untouched) |
| Primary color | Reuse the app's existing `brand` Tailwind token (blue), not a new indigo palette |
| Auth | Public, no middleware — same precedent as `/tv-dashboard` (wall-mounted display, no login) |
| Layout | No `AppLayout`/sidebar — full-screen standalone page, consistent with `/tv-dashboard` |
| Virtualization | `@tanstack/react-virtual` added as a real, declared dependency (hooks-based, fits this codebase's patterns; currently only a transitive/undeclared dependency via headlessui) |

## 1. Routing & page shell

- `routes/web.php`: `Route::get('/delivery-monitor', [DeliveryMonitorController::class, 'index'])->name('delivery-monitor');` placed outside the `auth` middleware group, next to the existing `tv-dashboard` route.
- `app/Http/Controllers/DeliveryMonitorController.php`: trivial for now — `return Inertia::render('DeliveryMonitor/Index');` with no props (mock data lives client-side this phase).
- `resources/js/Pages/DeliveryMonitor/Index.tsx`: no `.layout` assignment (intentionally standalone, matching `/tv-dashboard`).

## 2. Component structure

```
resources/js/Pages/DeliveryMonitor/
  Index.tsx                    — page shell, owns all top-level state
  components/
    Header.tsx                 — logo/title, supplier dropdown, date picker, TV/Rotate/Dark toggles, live clock, OTIF badge
    SupplierGrid.tsx            — search, filter tabs, summary badges, card grid
    SupplierCard.tsx            — one card: status dot, code, %, name, act/plan, progress bar
    SupplierDetailPanel.tsx     — focused-supplier header, 2 stat cards, scheduled-parts list, footer
    PartsTable.tsx               — search, category filter, status tabs, virtualized table, legend
  utils/
    mockData.ts                 — generator: 30 suppliers, ~1650 parts, cycles, receipts
    statusColor.ts               — single `getStatusColor(status)` used by every component
  types.ts                       — Supplier, DeliveryCycle, Part, PartCycleReceipt, and UI-level derived types
```

`types.ts` is written first (Stage 2) so the mock generator and every component (Stages 3–6) share one contract — this is also what makes swapping mock data for real backend data later a low-risk, mostly-mechanical change (same shape, different source).

## 3. Data model (mock-phase TypeScript shape, later becomes Eloquent)

```ts
type SupplierStatus = 'live' | 'alert' | 'done' | 'standby';

interface Supplier {
  id: number;
  code: string;        // "AAJ"
  name: string;         // "PT. ADI AJI JAYA"
  status: SupplierStatus;
}

interface DeliveryCycle {
  supplierId: number;
  cycleNumber: number;  // 1-6
  timeStart: string;     // "07:30"
  timeEnd: string;       // "09:30"
  planQty: number;
  actualQty: number;
}

interface Part {
  id: number;
  partNumber: string;
  partName: string;
  category: string;
  supplierId: number;
}

type ReceiptStatus = 'matched' | 'shortage' | 'pending' | 'over';

interface PartCycleReceipt {
  partId: number;
  cycleNumber: number;   // 1-6
  planQty: number;
  receivedQty: number;
  status: ReceiptStatus;
}
```

## 4. Status color mapping (single source of truth)

`utils/statusColor.ts` exports one `getStatusColor(status: SupplierStatus | ReceiptStatus)` returning a small object `{ dot, text, bg, border }` of Tailwind classes, built on the app's existing `brand`/`success`/`warning`/`error` tokens:
- `live` / `matched` / `done` → success (green)
- `alert` / `shortage` → error (red)
- `standby` / `pending` → gray
- `over` (over-delivery) / in-progress → warning (orange)

Every component (SupplierCard dot, PartsTable badges, legend) calls this one function — no ad-hoc color logic duplicated per component.

## 5. Stage-by-stage build order

Matches the user's requested order exactly; each stage ends with a working, visually-inspectable result before moving on.

1. **Scaffold**: route + controller + empty `Index.tsx` (renders a placeholder heading) + empty stub files for all 5 components (each returns a labeled placeholder `<div>`) + empty `types.ts`/`statusColor.ts`. Confirms `/delivery-monitor` loads.
2. **Mock data**: full `types.ts`, full `mockData.ts` generating the described volumes with realistic status distribution (a mix of live/alert/done/standby suppliers; a mix of matched/shortage/pending/over receipts) and a computed "today's OTIF" percentage. Verified via a temporary console-table or minimal render, not wired into real UI yet.
3. **Header**: full UI wired to mock data + local component state (selected supplier id, selected date, TV mode on/off, rotate on/off — dark mode via the existing global `useTheme()`), using the existing `date-picker.tsx` (flatpickr wrapper) and the existing lightweight `Dropdown` component for the supplier picker, plus a `setInterval`-based live clock (matching the existing TV dashboard's clock pattern).
4. **SupplierGrid + SupplierCard**: full grid with search (by code or name), filter tabs (All/Live/Alert/Done/Standby) computed from mock suppliers, summary badge counts, and card click → lifts the selected supplier id up to `Index.tsx` state (same state Header's dropdown also controls, so either can drive focus).
5. **SupplierDetailPanel**: renders for whichever supplier is currently focused (from shared state), including its Cycle 3 progress stat, day fulfillment stat + progress bar, and its scheduled-parts list for the current cycle, each with a progress bar (green+check when complete, orange+clock otherwise).
6. **PartsTable**: search (part number or name), category filter, status filter tabs, "Reset Receipts" action (resets mock receipt data), and the full C1–C6 grid columns each showing plan (small, gray, top) / actual (bold, colored, bottom, with a small status dot) — rendered through `@tanstack/react-virtual` row virtualization since the dataset is ~1650 rows and must never render everything to the DOM at once. Footer count + legend.

## 6. Testing / verification

This is a new, self-contained, presentational-only page with a client-side mock data source — no backend logic to unit test this phase. Verification per stage: `npm run build` (type/syntax check) plus a manual visual check (no browser automation available this session, same limitation as other frontend work this session — flagged explicitly, not silently skipped). Once the real backend phase begins later, standard PHPUnit feature tests will cover the new controller/models at that time.
