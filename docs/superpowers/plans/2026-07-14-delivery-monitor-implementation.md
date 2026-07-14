# Warehouse Part Delivery Monitor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans, executed **one task at a time with an explicit user confirmation checkpoint after each task** — this overrides the skill's default "execute all tasks then report" flow, per the user's explicit request. Do not start Task N+1 until the user has reviewed Task N.

**Goal:** Build the "Warehouse Part Delivery Monitor" TV dashboard page, frontend-only with mock data this phase, in 6 confirm-gated stages exactly as the user specified.

**Architecture:** A new standalone, public, no-auth Inertia page at `/delivery-monitor` (no `AppLayout`, matching the existing `/tv-dashboard` precedent), composed of 5 presentational components fed by a client-side mock data generator this phase. A shared `types.ts` contract means swapping mock data for real backend data later is a data-source swap, not a component rewrite.

**Tech Stack:** Laravel 11 (trivial controller), Inertia.js + React (TSX), Tailwind CSS (existing `brand`/`success`/`warning`/`error` tokens), `@tanstack/react-virtual` (new dependency), existing `date-picker.tsx` (flatpickr) and `Dropdown.jsx` components, existing global `ThemeContext` for dark mode.

## Global Constraints

- No `AppLayout`/sidebar wrapper on this page — full-screen standalone, public route, no auth middleware (matches `/tv-dashboard`).
- Primary color = the app's existing `brand` Tailwind token, not a new indigo palette.
- All status→color mapping goes through one `utils/statusColor.ts::getStatusColor()` function — never ad-hoc per component.
- `types.ts` is the single shared contract for `Supplier`/`DeliveryCycle`/`Part`/`PartCycleReceipt` — written once in Task 2, consumed unchanged by every later task.
- The parts table (Task 6) must use row virtualization (`@tanstack/react-virtual`) — never render all ~1650 rows to the DOM at once.
- **Execute one task, then stop and wait for explicit user confirmation before starting the next task.** This is a hard requirement from the user, not a suggestion.
- Verify each task with `npm run build`. No browser automation available this session — flag manual visual verification as the user's responsibility, don't claim to have seen it render.
- Spec reference: `docs/superpowers/specs/2026-07-14-delivery-monitor-design.md`

---

## Task 1: Routing + empty component scaffold

**Files:**
- Create: `app/Http/Controllers/DeliveryMonitorController.php`
- Create: `resources/js/Pages/DeliveryMonitor/Index.tsx`
- Create: `resources/js/Pages/DeliveryMonitor/components/Header.tsx`
- Create: `resources/js/Pages/DeliveryMonitor/components/SupplierGrid.tsx`
- Create: `resources/js/Pages/DeliveryMonitor/components/SupplierCard.tsx`
- Create: `resources/js/Pages/DeliveryMonitor/components/SupplierDetailPanel.tsx`
- Create: `resources/js/Pages/DeliveryMonitor/components/PartsTable.tsx`
- Create: `resources/js/Pages/DeliveryMonitor/utils/statusColor.ts`
- Create: `resources/js/Pages/DeliveryMonitor/types.ts` (empty shell, filled in Task 2)
- Modify: `routes/web.php`

**Interfaces:**
- Produces: route `delivery-monitor` (public, GET `/delivery-monitor`), rendering Inertia component `DeliveryMonitor/Index`.
- Produces: 5 placeholder component exports (`Header`, `SupplierGrid`, `SupplierCard`, `SupplierDetailPanel`, `PartsTable`), each a functional component accepting no required props yet, rendering a labeled placeholder — consumed and given real props/content in Tasks 3–6.
- Produces: `getStatusColor(status: string)` stub returning a default color object — given its real status-union signature in Task 2, real logic in later tasks.

- [ ] **Step 1: Add the route**

In `routes/web.php`, add near the existing `tv-dashboard` route (outside the `auth` middleware group):

```php
Route::get('/delivery-monitor', [App\Http\Controllers\DeliveryMonitorController::class, 'index'])->name('delivery-monitor');
```

- [ ] **Step 2: Create the controller**

Create `app/Http/Controllers/DeliveryMonitorController.php`:

```php
<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class DeliveryMonitorController extends Controller
{
    public function index()
    {
        return Inertia::render('DeliveryMonitor/Index');
    }
}
```

- [ ] **Step 3: Create the types shell**

Create `resources/js/Pages/DeliveryMonitor/types.ts`:

```ts
// Full data-model interfaces are added in Task 2 (mock data generator).
export type SupplierStatus = 'live' | 'alert' | 'done' | 'standby';
export type ReceiptStatus = 'matched' | 'shortage' | 'pending' | 'over';
```

- [ ] **Step 4: Create the status color util stub**

Create `resources/js/Pages/DeliveryMonitor/utils/statusColor.ts`:

```ts
import { SupplierStatus, ReceiptStatus } from '../types';

export interface StatusColorClasses {
    dot: string;
    text: string;
    bg: string;
    border: string;
}

// Full status→color mapping is implemented in Task 2/4 as each status set is wired up.
export function getStatusColor(status: SupplierStatus | ReceiptStatus): StatusColorClasses {
    return {
        dot: 'bg-gray-400',
        text: 'text-gray-600 dark:text-gray-300',
        bg: 'bg-gray-50 dark:bg-gray-800',
        border: 'border-gray-200 dark:border-gray-700',
    };
}
```

- [ ] **Step 5: Create the placeholder components**

Create `resources/js/Pages/DeliveryMonitor/components/Header.tsx`:

```tsx
export default function Header() {
    return (
        <div className="border-b border-gray-200 dark:border-gray-800 p-4">
            <p className="text-sm text-gray-400">Header — built in Stage 3</p>
        </div>
    );
}
```

Create `resources/js/Pages/DeliveryMonitor/components/SupplierGrid.tsx`:

```tsx
export default function SupplierGrid() {
    return (
        <div className="p-4">
            <p className="text-sm text-gray-400">SupplierGrid — built in Stage 4</p>
        </div>
    );
}
```

Create `resources/js/Pages/DeliveryMonitor/components/SupplierCard.tsx`:

```tsx
export default function SupplierCard() {
    return (
        <div className="p-4 rounded-xl border border-gray-200 dark:border-gray-700">
            <p className="text-sm text-gray-400">SupplierCard — built in Stage 4</p>
        </div>
    );
}
```

Create `resources/js/Pages/DeliveryMonitor/components/SupplierDetailPanel.tsx`:

```tsx
export default function SupplierDetailPanel() {
    return (
        <div className="p-4">
            <p className="text-sm text-gray-400">SupplierDetailPanel — built in Stage 5</p>
        </div>
    );
}
```

Create `resources/js/Pages/DeliveryMonitor/components/PartsTable.tsx`:

```tsx
export default function PartsTable() {
    return (
        <div className="p-4">
            <p className="text-sm text-gray-400">PartsTable — built in Stage 6</p>
        </div>
    );
}
```

- [ ] **Step 6: Create the page shell**

Create `resources/js/Pages/DeliveryMonitor/Index.tsx`:

```tsx
import React from 'react';
import { Head } from '@inertiajs/react';
import Header from './components/Header';
import SupplierGrid from './components/SupplierGrid';
import SupplierDetailPanel from './components/SupplierDetailPanel';
import PartsTable from './components/PartsTable';

export default function Index() {
    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
            <Head title="Warehouse Part Delivery Monitor" />
            <Header />
            <div className="grid grid-cols-1 lg:grid-cols-[65%_35%] gap-4 p-4">
                <SupplierGrid />
                <SupplierDetailPanel />
            </div>
            <div className="p-4">
                <PartsTable />
            </div>
        </div>
    );
}
```

Note: `Index.tsx` intentionally has **no** `.layout` static property assignment — this page is standalone (no `AppLayout`/sidebar), matching the existing `/tv-dashboard` page.

- [ ] **Step 7: Verify the route resolves**

Run: `php artisan route:list | grep delivery-monitor`
Expected: shows `GET|HEAD delivery-monitor ... delivery-monitor › DeliveryMonitorController@index`, no `auth` middleware listed.

- [ ] **Step 8: Build**

Run: `npm run build`
Expected: succeeds, no errors mentioning `DeliveryMonitor`.

- [ ] **Step 9: STOP — wait for user confirmation before Task 2**

Report to the user that `/delivery-monitor` now loads with the page shell and 5 labeled placeholders, and wait for explicit confirmation before starting Task 2 (mock data generator).

---

## Task 2: Mock data generator

**Files:**
- Modify: `resources/js/Pages/DeliveryMonitor/types.ts` (full interfaces)
- Modify: `resources/js/Pages/DeliveryMonitor/utils/statusColor.ts` (full mapping)
- Create: `resources/js/Pages/DeliveryMonitor/utils/mockData.ts`

**Deliverable:** a `generateMockData()` function returning ~30 `Supplier` records, their `DeliveryCycle` records (C1–C6 with time windows and plan/actual), ~1650 `Part` records distributed across suppliers, and `PartCycleReceipt` records per part per active cycle with a realistic mix of `matched`/`shortage`/`pending`/`over` statuses, plus a computed today's-OTIF percentage. Exact generation logic (distributions, category list, naming) to be written when this task starts, informed by how Task 1's shell renders — full code will follow the same "no placeholders, complete code" rule as every other task in this plan, written at execution time rather than duplicated here since it depends on the finalized `types.ts` shape.

**Interfaces:**
- Consumes: `SupplierStatus`, `ReceiptStatus` from Task 1's `types.ts`.
- Produces: `Supplier`, `DeliveryCycle`, `Part`, `PartCycleReceipt` full interfaces (extending Task 1's shell) and `generateMockData(): { suppliers: Supplier[]; cycles: DeliveryCycle[]; parts: Part[]; receipts: PartCycleReceipt[] }`. Consumed by `Index.tsx` and every component in Tasks 3–6.
- Produces: full `getStatusColor()` mapping (real Tailwind classes per status, using `brand`/`success`/`warning`/`error` tokens). Consumed by Tasks 4–6.

- [ ] **Step 1–N:** (written at execution time per the note above — verified via a temporary render/console check, then `npm run build`)
- [ ] **STOP — wait for user confirmation before Task 3.**

---

## Task 3: Header

**Files:**
- Modify: `resources/js/Pages/DeliveryMonitor/components/Header.tsx`
- Modify: `resources/js/Pages/DeliveryMonitor/Index.tsx` (lift shared state: selected supplier id, selected date, TV mode, rotate; wire mock data in)

**Deliverable:** full header per spec §5 stage 3 — logo/title/subtitle, supplier dropdown (existing `Dropdown` component), date picker (existing flatpickr `date-picker.tsx`), TV Mode / Rotate toggle buttons, dark mode toggle (existing global `useTheme()`), live clock (`setInterval`, matches existing TV dashboard's clock pattern), "TODAY'S OTIF" badge from mock data.

**Interfaces:**
- Consumes: `generateMockData()` output (called once in `Index.tsx`, passed down), `useTheme()` from `Tailadmin/context/ThemeContext`.
- Produces: `Header` props `{ suppliers, selectedSupplierId, onSelectSupplier, selectedDate, onSelectDate, tvMode, onToggleTvMode, rotate, onToggleRotate, otifPercent }` — consumed by `Index.tsx`; `onSelectSupplier` callback shape reused by Task 4's `SupplierCard` click handler.

- [ ] **Step 1–N:** (written at execution time, verified via `npm run build`)
- [ ] **STOP — wait for user confirmation before Task 4.**

---

## Task 4: SupplierGrid + SupplierCard

**Files:**
- Modify: `resources/js/Pages/DeliveryMonitor/components/SupplierGrid.tsx`
- Modify: `resources/js/Pages/DeliveryMonitor/components/SupplierCard.tsx`
- Modify: `resources/js/Pages/DeliveryMonitor/Index.tsx` (wire grid into shared selected-supplier state from Task 3)

**Deliverable:** full grid per spec §5 stage 4 — search (code or name), filter tabs (All/Live/Alert/Done/Standby) with counts, summary badges, 6-column responsive card grid, each card showing status dot / code / completion % / name / act-plan line + thin progress bar (or "No schedule in C{n}" + "Standby" label when no cycle data), selected-card blue border+highlight, click sets shared focus state (same state Task 3's header dropdown controls).

**Interfaces:**
- Consumes: mock `suppliers`/`cycles` data, `getStatusColor()`, shared `selectedSupplierId`/`onSelectSupplier` from Task 3.
- Produces: no new shared interfaces — this is a leaf consumer of the Task 2/3 contracts.

- [ ] **Step 1–N:** (written at execution time, verified via `npm run build`)
- [ ] **STOP — wait for user confirmation before Task 5.**

---

## Task 5: SupplierDetailPanel

**Files:**
- Modify: `resources/js/Pages/DeliveryMonitor/components/SupplierDetailPanel.tsx`

**Deliverable:** full panel per spec §5 stage 5 — header (code icon, "SUPPLIER PARTNER" label, name, "FOCUS" badge), two stat cards (Cycle 3 progress with OTIF badge; Day fulfillment with progress bar), "CYCLE 3 SCHEDULED PARTS ({n})" list (part number/name, act/plan fraction, full-width progress bar, green+check when complete / orange+clock otherwise), footer ("{completed} of {total} complete" + "Ledger >" link).

**Interfaces:**
- Consumes: mock `parts`/`receipts`/`cycles` data filtered by the shared focused-supplier id (from Task 3/4's state), `getStatusColor()`.
- Produces: none new — leaf consumer.

- [ ] **Step 1–N:** (written at execution time, verified via `npm run build`)
- [ ] **STOP — wait for user confirmation before Task 6.**

---

## Task 6: PartsTable with filters, search, and virtualization

**Files:**
- Modify: `resources/js/Pages/DeliveryMonitor/components/PartsTable.tsx`
- Modify: `package.json` (add `@tanstack/react-virtual`)

**Deliverable:** full table per spec §5 stage 6 — search (part number/name), category dropdown filter, status filter tabs (All/Active Planned Only/Shortage-Delayed/Matched OK/Over-Deliveries), "Reset Receipts" button, columns NO/PART NUMBER/SUPPLIER (badge+name)/PART NAME/CATEGORY (badge)/C1–C6 (each with time sub-header, plan on top gray-small, actual on bottom bold-colored-with-dot)/TOTAL STATUS (fraction + OK/Short badge), footer count, legend (🟢🔴🟠). Rows rendered via `@tanstack/react-virtual`'s `useVirtualizer` inside a fixed-height scroll container — never all ~1650 rows in the DOM at once.

**Interfaces:**
- Consumes: mock `parts`/`receipts` data, `getStatusColor()`.
- Produces: none new — leaf consumer, last task in this phase.

- [ ] **Step 1: Add the virtualization dependency**

Run: `npm install @tanstack/react-virtual`

- [ ] **Step 2–N:** (written at execution time, verified via `npm run build`)
- [ ] **Final step: report the full page as complete and ask whether to proceed to a future backend-integration phase (out of scope for this plan) or stop here.**
