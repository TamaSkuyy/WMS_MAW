# TV Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a public, unauthenticated slideshow page (`/tv-dashboard`) for TV/kiosk display that rotates through all active products (6 per slide, 8s interval) showing current stock and a short receive/ship history, refreshed live via a Laravel Reverb broadcast signal instead of polling.

**Architecture:** A new `StockChanged` broadcast-now event fires on a public Reverb channel after each successful stock mutation in the existing `CycleController`/`ShipmentController`. A new `TvDashboardController` builds per-product summary data and chunks it into slides for a new public route. A new standalone React page subscribes to the channel via the already-configured `window.Echo` and does an Inertia partial reload of the `slides` prop on signal, while a client-side timer independently rotates the currently displayed slide.

**Tech Stack:** Laravel 11 (PHP), Laravel Reverb (already configured), PHPUnit feature tests, Inertia.js + React (TypeScript), laravel-echo/pusher-js (already configured in `resources/js/bootstrap.js`).

## Global Constraints

- No new database tables/migrations — all data is derived from existing `Product`, `Stock`, `Cycle`, `CycleItem`, `Shipment`, `ShipmentItem` tables.
- `GET /tv-dashboard` must NOT have `auth`/`verified` middleware — it must be reachable with no login.
- The `StockChanged` event carries no payload — it's a pure refresh signal; the receiving page re-fetches via Inertia partial reload, not via broadcast payload data.
- Follow existing test conventions: PHPUnit class-based tests (`extends Tests\TestCase`, `use RefreshDatabase`), added to existing files where the spec calls for it (`tests/Feature/CycleControllerTest.php`, `tests/Feature/ShipmentControllerTest.php`) and a new `tests/Feature/TvDashboardControllerTest.php` for the new controller.
- Follow the existing Echo usage convention from `resources/js/Tailadmin/components/header/NotificationDropdown.tsx`: access Echo via `(window as any).Echo`, not a typed global declaration.
- Follow the existing vehicle-model label convention from `resources/js/Pages/Master/Products/Show.tsx`: `` `${brand} ${name}${suffix ? ' ' + suffix : ''}` ``.
- Run backend tests with `php artisan test tests/Feature/<File>.php --filter="..."`. Run frontend verification with `npm run build`.
- Spec reference: `docs/superpowers/specs/2026-07-06-tv-dashboard-design.md`

---

## File Structure

### Files to Create

```
app/Events/StockChanged.php                          [Public, non-queued broadcast signal event]
app/Http/Controllers/TvDashboardController.php        [Builds per-product slide data]
resources/js/Pages/TvDashboard/Index.tsx              [Full-screen slideshow page]
tests/Feature/TvDashboardControllerTest.php           [Tests for the new controller/route]
```

### Files to Modify

```
routes/web.php                                    [Add public GET /tv-dashboard route]
app/Http/Controllers/CycleController.php          [Dispatch StockChanged in receive()/quickReceiveStore()]
app/Http/Controllers/ShipmentController.php       [Dispatch StockChanged in ship()]
tests/Feature/CycleControllerTest.php             [New dispatch tests]
tests/Feature/ShipmentControllerTest.php          [New dispatch tests]
```

---

## Task 1: `StockChanged` broadcast event + dispatch wiring

**Files:**
- Create: `app/Events/StockChanged.php`
- Modify: `app/Http/Controllers/CycleController.php` (`receive()` around the `if (! $ok)` check, `quickReceiveStore()` after its `DB::transaction` call)
- Modify: `app/Http/Controllers/ShipmentController.php` (`ship()` around the `if (! $result['ok'])` check)
- Test: `tests/Feature/CycleControllerTest.php`, `tests/Feature/ShipmentControllerTest.php`

**Interfaces:**
- Produces: `App\Events\StockChanged` — a class implementing `Illuminate\Contracts\Broadcasting\ShouldBroadcastNow`, broadcasting on `new Channel('warehouse.stock')`, no constructor arguments, no public properties. Later tasks (Task 3, frontend) listen for this by its default broadcast name `StockChanged` (Echo's `.StockChanged` convention for non-namespaced listens).

- [ ] **Step 1: Write the failing tests for Cycle**

Add to `tests/Feature/CycleControllerTest.php`, just before the final closing `}` of the class:

```php
    public function test_receive_dispatches_stock_changed_event(): void
    {
        Event::fake([StockChanged::class]);

        $rack = Rack::factory()->create();
        $cycle = Cycle::factory()->create(['status' => 'draft']);
        $product = Product::factory()->create();
        $item = CycleItem::factory()->create([
            'cycle_id' => $cycle->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'received_quantity' => 0,
        ]);

        $this->actingAs($this->user)->post(route('cycles.receive', $cycle), [
            'items' => [[
                'id' => $item->id,
                'received_quantity' => 10,
                'rack_id' => $rack->id,
                'notes' => null,
            ]],
        ]);

        Event::assertDispatched(StockChanged::class);
    }

    public function test_receive_does_not_dispatch_stock_changed_event_when_rejected(): void
    {
        Event::fake([StockChanged::class]);

        $cycle = Cycle::factory()->create(['status' => 'completed']);

        $this->actingAs($this->user)->post(route('cycles.receive', $cycle), [
            'items' => [],
        ]);

        Event::assertNotDispatched(StockChanged::class);
    }

    public function test_quick_receive_dispatches_stock_changed_event(): void
    {
        Event::fake([StockChanged::class]);

        $supplier = Supplier::factory()->create();
        $rack = Rack::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($this->user)->post(route('cycles.quick-receive.store'), [
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 3],
            ],
        ]);

        Event::assertDispatched(StockChanged::class);
    }
```

Add these two imports to the top of `tests/Feature/CycleControllerTest.php`, alongside the existing `use` statements:

```php
use App\Events\StockChanged;
use Illuminate\Support\Facades\Event;
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/CycleControllerTest.php --filter="test_receive_dispatches_stock_changed_event|test_receive_does_not_dispatch_stock_changed_event_when_rejected|test_quick_receive_dispatches_stock_changed_event"`
Expected: FAIL — `App\Events\StockChanged` class does not exist yet (`Class "App\Events\StockChanged" not found`).

- [ ] **Step 3: Create the `StockChanged` event**

Create `app/Events/StockChanged.php`:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class StockChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function broadcastOn(): Channel
    {
        return new Channel('warehouse.stock');
    }
}
```

- [ ] **Step 4: Dispatch the event from `CycleController::receive()` and `quickReceiveStore()`**

In `app/Http/Controllers/CycleController.php`, add this import alongside the existing ones at the top of the file:

```php
use App\Events\StockChanged;
```

In `receive()`, change:

```php
        if (! $ok) {
            return back()->with('error', 'Cannot receive this cycle.');
        }

        return redirect()->route('cycles.show', $cycle)->with('success', 'Cycle completed. Stock updated.');
    }
```

to:

```php
        if (! $ok) {
            return back()->with('error', 'Cannot receive this cycle.');
        }

        event(new StockChanged());

        return redirect()->route('cycles.show', $cycle)->with('success', 'Cycle completed. Stock updated.');
    }
```

In `quickReceiveStore()`, change:

```php
            return $cycle;
        });

        return redirect()->route('cycles.show', $cycle)->with('success', 'Barang diterima. Stock diperbarui.');
    }
```

to:

```php
            return $cycle;
        });

        event(new StockChanged());

        return redirect()->route('cycles.show', $cycle)->with('success', 'Barang diterima. Stock diperbarui.');
    }
```

- [ ] **Step 5: Run the Cycle tests to verify they pass**

Run: `php artisan test tests/Feature/CycleControllerTest.php --filter="test_receive_dispatches_stock_changed_event|test_receive_does_not_dispatch_stock_changed_event_when_rejected|test_quick_receive_dispatches_stock_changed_event"`
Expected: PASS

- [ ] **Step 6: Write the failing tests for Shipment**

Add to `tests/Feature/ShipmentControllerTest.php`, just before the final closing `}` of the class:

```php
    public function test_ship_dispatches_stock_changed_event(): void
    {
        Event::fake([StockChanged::class]);

        $rack = Rack::factory()->create();
        $product = Product::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 20]);

        $shipment = Shipment::factory()->create(['status' => 'draft']);
        $shipment->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 8]);

        $this->actingAs($this->user)->post(route('shipments.ship', $shipment));

        Event::assertDispatched(StockChanged::class);
    }

    public function test_ship_does_not_dispatch_stock_changed_event_when_insufficient_stock(): void
    {
        Event::fake([StockChanged::class]);

        $rack = Rack::factory()->create();
        $product = Product::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 3]);

        $shipment = Shipment::factory()->create(['status' => 'draft']);
        $shipment->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 10]);

        $this->actingAs($this->user)->post(route('shipments.ship', $shipment));

        Event::assertNotDispatched(StockChanged::class);
    }
```

Add these two imports to the top of `tests/Feature/ShipmentControllerTest.php`, alongside the existing `use` statements:

```php
use App\Events\StockChanged;
use Illuminate\Support\Facades\Event;
```

- [ ] **Step 7: Run tests to verify they fail**

Run: `php artisan test tests/Feature/ShipmentControllerTest.php --filter="test_ship_dispatches_stock_changed_event|test_ship_does_not_dispatch_stock_changed_event_when_insufficient_stock"`
Expected: `test_ship_dispatches_stock_changed_event` FAILS (event never dispatched yet). `test_ship_does_not_dispatch_stock_changed_event_when_insufficient_stock` PASSES already (nothing dispatches yet at all) — that's fine, it becomes a regression guard once Step 8 wires the dispatch in.

- [ ] **Step 8: Dispatch the event from `ShipmentController::ship()`**

In `app/Http/Controllers/ShipmentController.php`, add this import alongside the existing ones at the top of the file:

```php
use App\Events\StockChanged;
```

Change:

```php
        if (! $result['ok']) {
            return back()->with('error', $result['error']);
        }

        return redirect()->route('shipments.show', $shipment)->with('success', 'Shipment processed. Stock deducted.');
    }
```

to:

```php
        if (! $result['ok']) {
            return back()->with('error', $result['error']);
        }

        event(new StockChanged());

        return redirect()->route('shipments.show', $shipment)->with('success', 'Shipment processed. Stock deducted.');
    }
```

- [ ] **Step 9: Run the Shipment tests to verify they pass**

Run: `php artisan test tests/Feature/ShipmentControllerTest.php --filter="test_ship_dispatches_stock_changed_event|test_ship_does_not_dispatch_stock_changed_event_when_insufficient_stock"`
Expected: PASS

- [ ] **Step 10: Run both full test files to check for regressions**

Run: `php artisan test tests/Feature/CycleControllerTest.php tests/Feature/ShipmentControllerTest.php`
Expected: PASS (all tests, including everything from the prior transaction-input-protection work)

- [ ] **Step 11: Commit**

```bash
git add app/Events/StockChanged.php app/Http/Controllers/CycleController.php app/Http/Controllers/ShipmentController.php tests/Feature/CycleControllerTest.php tests/Feature/ShipmentControllerTest.php
git commit -m "feat: broadcast StockChanged event after successful stock mutations"
```

---

## Task 2: `TvDashboardController` + public route

**Files:**
- Create: `app/Http/Controllers/TvDashboardController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/TvDashboardControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\Product`, `App\Models\Stock`, `App\Models\Cycle`, `App\Models\CycleItem`, `App\Models\Shipment`, `App\Models\ShipmentItem` (all existing).
- Produces: `GET /tv-dashboard` (route name `tv-dashboard`) rendering Inertia component `TvDashboard/Index` with a single prop `slides: array` — an array of slide-groups, each an array of up to 6 item objects shaped as:
  ```
  {
    id: int, part_number: string, name: string,
    vehicle_model: { brand: string, name: string, suffix: string|null } | null,
    total_stock: int,
    last_received: { quantity: int, date: string } | null,
    last_shipped: { quantity: int, date: string } | null,
  }
  ```
  Task 3 (frontend) consumes exactly this shape.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/TvDashboardControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\CycleItem;
use App\Models\Product;
use App\Models\Rack;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function slidesFrom($response): array
    {
        $page = json_decode(json_encode($response->viewData('page')), true);

        return $page['props']['slides'];
    }

    public function test_index_is_accessible_without_authentication(): void
    {
        $response = $this->get(route('tv-dashboard'));

        $response->assertStatus(200);
    }

    public function test_index_chunks_active_products_into_slides_of_six(): void
    {
        Product::factory(8)->create(['is_active' => true]);

        $response = $this->get(route('tv-dashboard'));

        $slides = $this->slidesFrom($response);

        $this->assertCount(2, $slides);
        $this->assertCount(6, $slides[0]);
        $this->assertCount(2, $slides[1]);
    }

    public function test_index_excludes_inactive_products(): void
    {
        Product::factory()->create(['is_active' => true, 'name' => 'Active Item']);
        Product::factory()->create(['is_active' => false, 'name' => 'Inactive Item']);

        $response = $this->get(route('tv-dashboard'));

        $names = collect($this->slidesFrom($response))->flatten(1)->pluck('name');

        $this->assertTrue($names->contains('Active Item'));
        $this->assertFalse($names->contains('Inactive Item'));
    }

    public function test_index_includes_total_stock_summed_across_racks(): void
    {
        $product = Product::factory()->create();
        $rackA = Rack::factory()->create();
        $rackB = Rack::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rackA->id, 'quantity' => 5]);
        Stock::create(['product_id' => $product->id, 'rack_id' => $rackB->id, 'quantity' => 7]);

        $response = $this->get(route('tv-dashboard'));

        $item = collect($this->slidesFrom($response))->flatten(1)->firstWhere('id', $product->id);

        $this->assertSame(12, $item['total_stock']);
    }

    public function test_index_includes_last_received_summary_for_product_with_completed_cycle(): void
    {
        $product = Product::factory()->create();
        $cycle = Cycle::factory()->create(['status' => 'completed', 'received_at' => '2026-07-01 10:00:00']);
        CycleItem::factory()->create([
            'cycle_id' => $cycle->id,
            'product_id' => $product->id,
            'received_quantity' => 15,
        ]);

        $response = $this->get(route('tv-dashboard'));

        $item = collect($this->slidesFrom($response))->flatten(1)->firstWhere('id', $product->id);

        $this->assertSame(15, $item['last_received']['quantity']);
    }

    public function test_index_includes_null_last_received_for_product_without_history(): void
    {
        $product = Product::factory()->create();

        $response = $this->get(route('tv-dashboard'));

        $item = collect($this->slidesFrom($response))->flatten(1)->firstWhere('id', $product->id);

        $this->assertNull($item['last_received']);
    }

    public function test_index_includes_last_shipped_summary_for_product_with_shipped_shipment(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();
        $shipment = Shipment::factory()->create(['status' => 'shipped']);
        ShipmentItem::factory()->create([
            'shipment_id' => $shipment->id,
            'product_id' => $product->id,
            'rack_id' => $rack->id,
            'quantity' => 9,
        ]);

        $response = $this->get(route('tv-dashboard'));

        $item = collect($this->slidesFrom($response))->flatten(1)->firstWhere('id', $product->id);

        $this->assertSame(9, $item['last_shipped']['quantity']);
    }

    public function test_index_includes_null_last_shipped_for_product_without_history(): void
    {
        $product = Product::factory()->create();

        $response = $this->get(route('tv-dashboard'));

        $item = collect($this->slidesFrom($response))->flatten(1)->firstWhere('id', $product->id);

        $this->assertNull($item['last_shipped']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/TvDashboardControllerTest.php`
Expected: FAIL — route `tv-dashboard` does not exist yet (`Route [tv-dashboard] not defined`).

- [ ] **Step 3: Create `TvDashboardController`**

Create `app/Http/Controllers/TvDashboardController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\CycleItem;
use App\Models\Product;
use App\Models\ShipmentItem;
use App\Models\Stock;
use Inertia\Inertia;

class TvDashboardController extends Controller
{
    public function index()
    {
        $products = Product::with('vehicleModel')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $items = $products->map(function (Product $product) {
            $totalStock = (int) Stock::where('product_id', $product->id)->sum('quantity');

            $lastReceivedItem = CycleItem::where('product_id', $product->id)
                ->where('received_quantity', '>', 0)
                ->whereHas('cycle', fn ($q) => $q->where('status', 'completed'))
                ->with('cycle')
                ->orderByDesc('updated_at')
                ->first();

            $lastShippedItem = ShipmentItem::where('product_id', $product->id)
                ->whereHas('shipment', fn ($q) => $q->where('status', 'shipped'))
                ->with('shipment')
                ->orderByDesc('updated_at')
                ->first();

            return [
                'id' => $product->id,
                'part_number' => $product->part_number,
                'name' => $product->name,
                'vehicle_model' => $product->vehicleModel ? [
                    'brand' => $product->vehicleModel->brand,
                    'name' => $product->vehicleModel->name,
                    'suffix' => $product->vehicleModel->suffix,
                ] : null,
                'total_stock' => $totalStock,
                'last_received' => $lastReceivedItem ? [
                    'quantity' => $lastReceivedItem->received_quantity,
                    'date' => optional($lastReceivedItem->cycle->received_at)->toIso8601String(),
                ] : null,
                'last_shipped' => $lastShippedItem ? [
                    'quantity' => $lastShippedItem->quantity,
                    'date' => $lastShippedItem->shipment->updated_at->toIso8601String(),
                ] : null,
            ];
        });

        $slides = $items->chunk(6)->values()->map(fn ($chunk) => $chunk->values())->all();

        return Inertia::render('TvDashboard/Index', [
            'slides' => $slides,
        ]);
    }
}
```

- [ ] **Step 4: Add the public route**

In `routes/web.php`, add this line directly after the existing `/dashboard` route (still outside the `Route::middleware('auth')->group(...)` block):

```php
Route::get('/tv-dashboard', [App\Http\Controllers\TvDashboardController::class, 'index'])->name('tv-dashboard');
```

So the top of the file reads:

```php
Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/tv-dashboard', [App\Http\Controllers\TvDashboardController::class, 'index'])->name('tv-dashboard');

Route::middleware('auth')->group(function () {
```

- [ ] **Step 5: Create a placeholder page component so Inertia can resolve it**

Create `resources/js/Pages/TvDashboard/Index.tsx` with a minimal placeholder (Task 3 replaces this with the full slideshow):

```tsx
export default function Index() {
    return <div>TV Dashboard placeholder</div>;
}
```

(This file is fully rewritten in Task 3 — it exists now only so Inertia's page-finder doesn't fail the controller test.)

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/TvDashboardControllerTest.php`
Expected: PASS

- [ ] **Step 7: Run the full test suite to check for regressions**

Run: `php artisan test`
Expected: PASS (all tests across the whole app)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/TvDashboardController.php routes/web.php resources/js/Pages/TvDashboard/Index.tsx tests/Feature/TvDashboardControllerTest.php
git commit -m "feat: add public TV dashboard route with per-product slide data"
```

---

## Task 3: TV dashboard slideshow page (frontend)

**Files:**
- Modify (full rewrite of the Task 2 placeholder): `resources/js/Pages/TvDashboard/Index.tsx`

**Interfaces:**
- Consumes: the `slides` prop shape produced by `TvDashboardController::index()` in Task 2.
- Consumes: `(window as any).Echo` — already configured in `resources/js/bootstrap.js`, same access pattern as `resources/js/Tailadmin/components/header/NotificationDropdown.tsx`.

- [ ] **Step 1: Write the full slideshow page**

Replace the entire contents of `resources/js/Pages/TvDashboard/Index.tsx` with:

```tsx
import React, { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';

interface VehicleModelInfo {
    brand: string;
    name: string;
    suffix: string | null;
}

interface MovementInfo {
    quantity: number;
    date: string;
}

interface TvItem {
    id: number;
    part_number: string;
    name: string;
    vehicle_model: VehicleModelInfo | null;
    total_stock: number;
    last_received: MovementInfo | null;
    last_shipped: MovementInfo | null;
}

interface Props {
    slides: TvItem[][];
}

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

function vehicleModelLabel(vm: VehicleModelInfo | null): string {
    if (!vm) return '-';
    return `${vm.brand} ${vm.name}${vm.suffix ? ' ' + vm.suffix : ''}`;
}

export default function Index({ slides }: Props) {
    const [currentSlideIndex, setCurrentSlideIndex] = useState(0);

    useEffect(() => {
        const interval = setInterval(() => {
            setCurrentSlideIndex((prev) => (slides.length === 0 ? 0 : (prev + 1) % slides.length));
        }, 8000);
        return () => clearInterval(interval);
    }, [slides.length]);

    useEffect(() => {
        setCurrentSlideIndex((prev) => Math.min(prev, Math.max(slides.length - 1, 0)));
    }, [slides.length]);

    useEffect(() => {
        const Echo = (window as any).Echo;
        if (!Echo) return;

        const channel = Echo.channel('warehouse.stock');
        channel.listen('.StockChanged', () => {
            router.reload({ only: ['slides'], preserveState: true, preserveScroll: true });
        });

        return () => {
            Echo.leave('warehouse.stock');
        };
    }, []);

    const currentItems = slides[currentSlideIndex] ?? [];

    return (
        <>
            <Head title="TV Dashboard" />
            <div className="min-h-screen bg-[#0B1120] text-white p-10 flex flex-col">
                <div className="flex items-center justify-between mb-8">
                    <h1 className="text-4xl font-bold">Monitoring Gudang</h1>
                    <div className="text-xl text-gray-400">
                        Slide {slides.length === 0 ? 0 : currentSlideIndex + 1} / {slides.length}
                    </div>
                </div>
                <div className="grid grid-cols-3 grid-rows-2 gap-6 flex-1">
                    {currentItems.map((item) => (
                        <div key={item.id} className="rounded-2xl bg-white/5 border border-white/10 p-6 flex flex-col justify-between">
                            <div>
                                <div className="text-sm text-gray-400 font-mono">{item.part_number}</div>
                                <div className="text-2xl font-semibold mt-1">{item.name}</div>
                                <div className="text-lg text-gray-400 mt-1">{vehicleModelLabel(item.vehicle_model)}</div>
                            </div>
                            <div className="mt-6 space-y-2">
                                <div className="text-3xl font-bold text-brand-400">{item.total_stock} pcs</div>
                                <div className="text-base text-gray-300">
                                    Terakhir masuk: {item.last_received ? `${item.last_received.quantity} pcs — ${formatDate(item.last_received.date)}` : 'Belum ada data'}
                                </div>
                                <div className="text-base text-gray-300">
                                    Terakhir keluar: {item.last_shipped ? `${item.last_shipped.quantity} pcs — ${formatDate(item.last_shipped.date)}` : 'Belum ada data'}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}
```

- [ ] **Step 2: Build to verify no type/syntax errors**

Run: `npm run build`
Expected: build succeeds with no errors mentioning `TvDashboard/Index.tsx`.

- [ ] **Step 3: Run the backend TV dashboard tests again to confirm the real page still satisfies them**

Run: `php artisan test tests/Feature/TvDashboardControllerTest.php`
Expected: PASS (the Inertia page-finder now resolves the real component instead of the Task 2 placeholder)

- [ ] **Step 4: Manual verification**

With the local dev server running (`dev-local.sh`), open `http://localhost:<APP_PORT>/tv-dashboard` in a browser **without logging in first** — confirm it loads (no redirect to login) and shows a 3x2 grid of product cards that auto-advances every 8 seconds, wrapping back to the first slide after the last one. Then, in a separate logged-in tab, complete a Cycle receive (or Quick Receive) or ship a Shipment, and confirm the TV dashboard tab updates its numbers within a second or two without a full page reload (no visible flash/navigation, slide position keeps rotating normally).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/TvDashboard/Index.tsx
git commit -m "feat: build TV dashboard slideshow with live Reverb-driven refresh"
```
