# Transaction Input Protection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add duplicate-item merging, DB-transaction/row-locking around stock mutations, and frontend double-submit guards to the Cycles (receiving) and Shipments (outgoing) transaction flows.

**Architecture:** Backend-first enforcement in `CycleController` and `ShipmentController` (the source of truth), with a matching lightweight client-side `submitting` guard added to the pages that don't already have one. No schema or model changes.

**Tech Stack:** Laravel 11 (PHP), PHPUnit feature tests, Inertia.js + React (TypeScript).

## Global Constraints

- No new migrations, no schema changes — everything is application-level logic.
- Follow existing test conventions: PHPUnit class-based tests (`extends Tests\TestCase`, `use RefreshDatabase`), added to the existing `tests/Feature/CycleControllerTest.php` and `tests/Feature/ShipmentControllerTest.php` files — do not create new test files.
- Follow the existing frontend `submitting` state pattern already used in `resources/js/Pages/Transactions/Shipments/Create.tsx` and `resources/js/Pages/Transactions/Cycles/QuickReceive.tsx`.
- Run backend tests with `php artisan test tests/Feature/<File>.php --filter="..."`. Run frontend verification with `npm run build`.
- Spec reference: `docs/superpowers/specs/2026-07-05-transaction-input-protection-design.md`

---

## File Structure

### Files to Modify

```
app/Http/Controllers/CycleController.php       [dedupe helper; lockForUpdate in receive()/quickReceiveStore()]
app/Http/Controllers/ShipmentController.php    [dedupe helper; lockForUpdate in ship(); add DB import]
tests/Feature/CycleControllerTest.php          [new test cases appended]
tests/Feature/ShipmentControllerTest.php       [new test cases appended]
resources/js/Pages/Transactions/Cycles/Create.tsx   [processing guard on submit button]
resources/js/Pages/Transactions/Cycles/Edit.tsx     [processing guard on submit button]
resources/js/Pages/Transactions/Cycles/Show.tsx     [submitting guard on receive form]
resources/js/Pages/Transactions/Shipments/Show.tsx  [submitting guard on ship button]
```

---

## Task 1: Cycle item dedupe + sum (`store`/`update`)

**Files:**
- Modify: `app/Http/Controllers/CycleController.php:41-67` (`store`), `:101-132` (`update`)
- Test: `tests/Feature/CycleControllerTest.php`

**Interfaces:**
- Produces: `CycleController::mergeDuplicateItems(array $items): array` — private helper, groups by `product_id`, sums `quantity`, returns re-indexed array. Used by both `store()` and `update()`.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/CycleControllerTest.php`, just before the final closing `}` of the class:

```php
    public function test_store_merges_duplicate_product_items(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $data = [
            'supplier_id' => $supplier->id,
            'cycle_number' => 1,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10],
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('cycles.store'), $data);

        $response->assertRedirect();
        $cycle = Cycle::first();
        $this->assertCount(1, $cycle->items);
        $this->assertDatabaseHas('cycle_items', [
            'cycle_id' => $cycle->id,
            'product_id' => $product->id,
            'quantity' => 15,
        ]);
    }

    public function test_update_merges_duplicate_product_items(): void
    {
        $cycle = Cycle::factory()->create(['status' => 'draft']);
        $product = Product::factory()->create();

        $data = [
            'supplier_id' => $cycle->supplier_id,
            'cycle_number' => $cycle->cycle_number,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 4],
                ['product_id' => $product->id, 'quantity' => 6],
            ],
        ];

        $response = $this->actingAs($this->user)->put(route('cycles.update', $cycle), $data);

        $response->assertRedirect();
        $this->assertCount(1, $cycle->fresh()->items);
        $this->assertDatabaseHas('cycle_items', [
            'cycle_id' => $cycle->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/CycleControllerTest.php --filter="test_store_merges_duplicate_product_items|test_update_merges_duplicate_product_items"`
Expected: both FAIL — `assertCount(1, ...)` sees 2 items instead of 1 (no merging happens yet).

- [ ] **Step 3: Implement the merge helper and wire it into `store`/`update`**

In `app/Http/Controllers/CycleController.php`, add this private method inside the `CycleController` class (e.g. directly above `store`):

```php
    private function mergeDuplicateItems(array $items): array
    {
        $merged = [];
        foreach ($items as $item) {
            $key = $item['product_id'];
            if (isset($merged[$key])) {
                $merged[$key]['quantity'] += $item['quantity'];
            } else {
                $merged[$key] = $item;
            }
        }
        return array_values($merged);
    }
```

In `store()`, right after the `$validated = $request->validate([...]);` block, add:

```php
        $validated['items'] = $this->mergeDuplicateItems($validated['items']);
```

Do the same in `update()`, right after its own `$validated = $request->validate([...]);` block.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/CycleControllerTest.php --filter="test_store_merges_duplicate_product_items|test_update_merges_duplicate_product_items"`
Expected: PASS

- [ ] **Step 5: Run the full Cycle test file to check for regressions**

Run: `php artisan test tests/Feature/CycleControllerTest.php`
Expected: PASS (all tests, including pre-existing ones)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/CycleController.php tests/Feature/CycleControllerTest.php
git commit -m "feat: merge duplicate product items in cycle store/update"
```

---

## Task 2: Shipment item dedupe + sum (`store`/`update`)

**Files:**
- Modify: `app/Http/Controllers/ShipmentController.php:38-66` (`store`), `:89-121` (`update`)
- Test: `tests/Feature/ShipmentControllerTest.php`

**Interfaces:**
- Produces: `ShipmentController::mergeDuplicateItems(array $items): array` — private helper, groups by `product_id . '-' . rack_id`, sums `quantity`, returns re-indexed array. Used by both `store()` and `update()`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/ShipmentControllerTest.php`, just before the final closing `}` of the class:

```php
    public function test_store_merges_duplicate_product_and_rack_items(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $data = [
            'partner_name' => 'PT Test Partner',
            'shipment_date' => '2026-06-10',
            'items' => [
                ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5],
                ['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 3],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('shipments.store'), $data);

        $response->assertRedirect();
        $shipment = Shipment::first();
        $this->assertCount(1, $shipment->items);
        $this->assertDatabaseHas('shipment_items', [
            'shipment_id' => $shipment->id,
            'product_id' => $product->id,
            'rack_id' => $rack->id,
            'quantity' => 8,
        ]);
    }

    public function test_store_keeps_same_product_different_rack_as_separate_items(): void
    {
        $product = Product::factory()->create();
        $rackA = Rack::factory()->create();
        $rackB = Rack::factory()->create();

        $data = [
            'partner_name' => 'PT Test Partner',
            'shipment_date' => '2026-06-10',
            'items' => [
                ['product_id' => $product->id, 'rack_id' => $rackA->id, 'quantity' => 5],
                ['product_id' => $product->id, 'rack_id' => $rackB->id, 'quantity' => 3],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('shipments.store'), $data);

        $response->assertRedirect();
        $shipment = Shipment::first();
        $this->assertCount(2, $shipment->items);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/ShipmentControllerTest.php --filter="test_store_merges_duplicate_product_and_rack_items|test_store_keeps_same_product_different_rack_as_separate_items"`
Expected: `test_store_merges_duplicate_product_and_rack_items` FAILS (`assertCount(1, ...)` sees 2). `test_store_keeps_same_product_different_rack_as_separate_items` PASSES already (no merge needed yet) — that's fine, it becomes a regression guard.

- [ ] **Step 3: Implement the merge helper and wire it into `store`/`update`**

In `app/Http/Controllers/ShipmentController.php`, add this private method inside the `ShipmentController` class (e.g. directly above `store`):

```php
    private function mergeDuplicateItems(array $items): array
    {
        $merged = [];
        foreach ($items as $item) {
            $key = $item['product_id'] . '-' . $item['rack_id'];
            if (isset($merged[$key])) {
                $merged[$key]['quantity'] += $item['quantity'];
            } else {
                $merged[$key] = $item;
            }
        }
        return array_values($merged);
    }
```

In `store()`, right after its `$validated = $request->validate([...]);` block, add:

```php
        $validated['items'] = $this->mergeDuplicateItems($validated['items']);
```

Do the same in `update()`, right after its own `$validated = $request->validate([...]);` block.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ShipmentControllerTest.php --filter="test_store_merges_duplicate_product_and_rack_items|test_store_keeps_same_product_different_rack_as_separate_items"`
Expected: PASS

- [ ] **Step 5: Run the full Shipment test file to check for regressions**

Run: `php artisan test tests/Feature/ShipmentControllerTest.php`
Expected: PASS (all tests, including pre-existing ones)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ShipmentController.php tests/Feature/ShipmentControllerTest.php
git commit -m "feat: merge duplicate product+rack items in shipment store/update"
```

---

## Task 3: Race condition protection — `CycleController::receive()` and `quickReceiveStore()`

**Files:**
- Modify: `app/Http/Controllers/CycleController.php:146-188` (`receive`), `:199-240` (`quickReceiveStore`)
- Test: `tests/Feature/CycleControllerTest.php`

**Interfaces:**
- Consumes: none beyond existing `Cycle`, `CycleItem`, `Stock` models and `DB` facade (already imported in this file).
- Produces: `receive()` keeps its existing signature/route/behavior for the happy path; on a second call after the cycle already left `draft`/`receiving`, it now returns `back()->with('error', 'Cannot receive this cycle.')` — same message as the pre-existing early guard, so no frontend change is needed.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/CycleControllerTest.php`:

```php
    public function test_receive_second_call_is_rejected_and_stock_not_doubled(): void
    {
        $rack = Rack::factory()->create();
        $cycle = Cycle::factory()->create(['status' => 'draft']);
        $product = Product::factory()->create();
        $item = CycleItem::factory()->create([
            'cycle_id' => $cycle->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'received_quantity' => 0,
        ]);

        $payload = [
            'items' => [[
                'id' => $item->id,
                'received_quantity' => 10,
                'rack_id' => $rack->id,
                'notes' => null,
            ]],
        ];

        $first = $this->actingAs($this->user)->post(route('cycles.receive', $cycle), $payload);
        $first->assertRedirect();

        $second = $this->actingAs($this->user)->post(route('cycles.receive', $cycle), $payload);
        $second->assertRedirect();
        $second->assertSessionHas('error');

        $this->assertDatabaseHas('stocks', [
            'product_id' => $product->id,
            'rack_id' => $rack->id,
            'quantity' => 10,
        ]);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/CycleControllerTest.php --filter="test_receive_second_call_is_rejected_and_stock_not_doubled"`
Expected: FAIL — today the second call re-enters `receive()`, finds `status === 'completed'`, is already caught by the existing top-of-method guard... Note: check this carefully. The existing guard (`if ($cycle->status !== 'draft' && $cycle->status !== 'receiving')`) already runs on the *route-model-bound* `$cycle`, which was fetched fresh per request — after the first call sets status to `completed`, the second request's `$cycle` binding will already read `completed` from the DB, so this specific test may PASS even before Task 3's transaction change (this guard is not the actual race — it protects sequential calls already). Run it first to confirm; if it already passes, proceed to Step 3 anyway since the transaction/locking is still required to protect true concurrent requests (which this sequential test cannot exercise), then re-run in Step 4 to confirm it still passes after the change.

- [ ] **Step 3: Wrap `receive()` in a locked DB transaction**

Replace the full body of `receive()` in `app/Http/Controllers/CycleController.php` with:

```php
    public function receive(Request $request, Cycle $cycle)
    {
        if ($cycle->status !== 'draft' && $cycle->status !== 'receiving') {
            return back()->with('error', 'Cannot receive this cycle.');
        }

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:cycle_items,id',
            'items.*.received_quantity' => 'required|integer|min:0',
            'items.*.rack_id' => 'required|exists:racks,id',
            'items.*.notes' => 'nullable|string|max:200',
        ]);

        $ok = DB::transaction(function () use ($validated, $cycle) {
            $lockedCycle = Cycle::where('id', $cycle->id)->lockForUpdate()->firstOrFail();

            if ($lockedCycle->status !== 'draft' && $lockedCycle->status !== 'receiving') {
                return false;
            }

            foreach ($validated['items'] as $itemData) {
                $item = CycleItem::where('id', $itemData['id'])
                    ->where('cycle_id', $lockedCycle->id)
                    ->firstOrFail();
                $item->update([
                    'received_quantity' => $itemData['received_quantity'],
                    'rack_id' => $itemData['rack_id'],
                    'notes' => $itemData['notes'] ?? null,
                ]);

                if ($itemData['received_quantity'] > 0) {
                    $stock = Stock::where('product_id', $item->product_id)
                        ->where('rack_id', $itemData['rack_id'])
                        ->lockForUpdate()
                        ->first();

                    if (! $stock) {
                        $stock = Stock::create([
                            'product_id' => $item->product_id,
                            'rack_id' => $itemData['rack_id'],
                            'quantity' => 0,
                        ]);
                    }

                    $stock->quantity += $itemData['received_quantity'];
                    $stock->save();
                }
            }

            $lockedCycle->update([
                'status' => 'completed',
                'received_at' => now(),
            ]);

            return true;
        });

        if (! $ok) {
            return back()->with('error', 'Cannot receive this cycle.');
        }

        return redirect()->route('cycles.show', $cycle)->with('success', 'Cycle completed. Stock updated.');
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/CycleControllerTest.php --filter="test_receive_second_call_is_rejected_and_stock_not_doubled"`
Expected: PASS

- [ ] **Step 5: Add `lockForUpdate()` to `quickReceiveStore()`'s stock lookup**

In `app/Http/Controllers/CycleController.php`, inside `quickReceiveStore()`, replace:

```php
                $stock = Stock::firstOrNew([
                    'product_id' => $item['product_id'],
                    'rack_id'    => $item['rack_id'],
                ]);
                $stock->quantity += $item['quantity'];
                $stock->save();
```

with:

```php
                $stock = Stock::where('product_id', $item['product_id'])
                    ->where('rack_id', $item['rack_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $stock) {
                    $stock = Stock::create([
                        'product_id' => $item['product_id'],
                        'rack_id'    => $item['rack_id'],
                        'quantity'   => 0,
                    ]);
                }

                $stock->quantity += $item['quantity'];
                $stock->save();
```

- [ ] **Step 6: Run the full Cycle test file to check for regressions**

Run: `php artisan test tests/Feature/CycleControllerTest.php`
Expected: PASS (all tests, including `test_quick_receive_creates_cycle_and_updates_stock` and the Task 1 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/CycleController.php tests/Feature/CycleControllerTest.php
git commit -m "feat: lock cycle/stock rows during receive to prevent double stock updates"
```

---

## Task 4: Race condition protection — `ShipmentController::ship()`

**Files:**
- Modify: `app/Http/Controllers/ShipmentController.php:1-11` (imports), `:137-174` (`ship`)
- Test: `tests/Feature/ShipmentControllerTest.php`

**Interfaces:**
- Produces: `ship()` keeps its existing signature/route/behavior for the happy path and for the "insufficient stock" path (same error message format). On a second call after the shipment already left `draft`, it now returns `back()->with('error', 'Cannot ship this shipment.')` — same message as the pre-existing early guard.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/ShipmentControllerTest.php`:

```php
    public function test_ship_second_call_is_rejected_and_stock_not_double_deducted(): void
    {
        $rack = Rack::factory()->create();
        $product = Product::factory()->create();
        Stock::create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 20]);

        $shipment = Shipment::factory()->create(['status' => 'draft']);
        $shipment->items()->create(['product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 8]);

        $first = $this->actingAs($this->user)->post(route('shipments.ship', $shipment));
        $first->assertRedirect();

        $second = $this->actingAs($this->user)->post(route('shipments.ship', $shipment));
        $second->assertRedirect();
        $second->assertSessionHas('error');

        $this->assertDatabaseHas('stocks', [
            'product_id' => $product->id,
            'rack_id' => $rack->id,
            'quantity' => 12,
        ]);
    }
```

- [ ] **Step 2: Run test to verify it fails or already passes**

Run: `php artisan test tests/Feature/ShipmentControllerTest.php --filter="test_ship_second_call_is_rejected_and_stock_not_double_deducted"`
As with Task 3 Step 2: the existing top-of-method status guard may already make this specific sequential test pass. Proceed to Step 3 regardless — the transaction/locking protects true concurrent requests, which this sequential test cannot exercise on its own.

- [ ] **Step 3: Add the `DB` facade import**

In `app/Http/Controllers/ShipmentController.php`, add this import alongside the existing ones (after `use Illuminate\Http\Request;`):

```php
use Illuminate\Support\Facades\DB;
```

- [ ] **Step 4: Wrap `ship()` in a locked DB transaction**

Replace the full body of `ship()` in `app/Http/Controllers/ShipmentController.php` with:

```php
    public function ship(Request $request, Shipment $shipment)
    {
        if ($shipment->status !== 'draft') {
            return back()->with('error', 'Cannot ship this shipment.');
        }

        $result = DB::transaction(function () use ($shipment) {
            $lockedShipment = Shipment::where('id', $shipment->id)->lockForUpdate()->firstOrFail();

            if ($lockedShipment->status !== 'draft') {
                return ['ok' => false, 'error' => 'Cannot ship this shipment.'];
            }

            $items = $lockedShipment->items()->with('product', 'rack')->get();
            $lockedStocks = [];

            foreach ($items as $item) {
                $stock = Stock::where('product_id', $item->product_id)
                    ->where('rack_id', $item->rack_id)
                    ->lockForUpdate()
                    ->first();

                if (! $stock || $stock->quantity < $item->quantity) {
                    $productName = $item->product->name ?? 'Unknown';
                    $rackCode = $item->rack->code ?? '?';

                    return [
                        'ok' => false,
                        'error' => "Insufficient stock: {$productName} in rack {$rackCode}. Available: "
                            . ($stock->quantity ?? 0)
                            . ", Requested: {$item->quantity}",
                    ];
                }

                $lockedStocks[$item->id] = $stock;
            }

            foreach ($items as $item) {
                $stock = $lockedStocks[$item->id];
                $stock->quantity -= $item->quantity;
                $stock->save();
            }

            $lockedShipment->update(['status' => 'shipped']);

            return ['ok' => true];
        });

        if (! $result['ok']) {
            return back()->with('error', $result['error']);
        }

        return redirect()->route('shipments.show', $shipment)->with('success', 'Shipment processed. Stock deducted.');
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/ShipmentControllerTest.php --filter="test_ship_second_call_is_rejected_and_stock_not_double_deducted"`
Expected: PASS

- [ ] **Step 6: Run the full Shipment test file to check for regressions**

Run: `php artisan test tests/Feature/ShipmentControllerTest.php`
Expected: PASS (all tests, including `test_ship_deducts_stock` and `test_ship_fails_on_insufficient_stock`)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ShipmentController.php tests/Feature/ShipmentControllerTest.php
git commit -m "feat: lock shipment/stock rows during ship to prevent double stock deduction"
```

---

## Task 5: Double-submit guard — `Cycles/Create.tsx` and `Cycles/Edit.tsx`

**Files:**
- Modify: `resources/js/Pages/Transactions/Cycles/Create.tsx:14,124`
- Modify: `resources/js/Pages/Transactions/Cycles/Edit.tsx:14,96`

**Interfaces:**
- Consumes: Inertia's `useForm()` return value, which already includes a `processing` boolean (standard Inertia React API) — no new state needed since both files already use `useForm`.

- [ ] **Step 1: Add `processing` to the destructured `useForm` result in `Create.tsx`**

In `resources/js/Pages/Transactions/Cycles/Create.tsx`, change:

```tsx
    const { data, setData, post, errors } = useForm({
```

to:

```tsx
    const { data, setData, post, errors, processing } = useForm({
```

- [ ] **Step 2: Disable the submit button while processing in `Create.tsx`**

Change:

```tsx
                    <Button type="submit" disabled={data.items.length === 0} icon={<CheckIcon className="w-4 h-4" />}>Simpan Cycle</Button>
```

to:

```tsx
                    <Button type="submit" disabled={data.items.length === 0 || processing} icon={<CheckIcon className="w-4 h-4" />}>
                        {processing ? 'Menyimpan...' : 'Simpan Cycle'}
                    </Button>
```

- [ ] **Step 3: Apply the same two changes to `Edit.tsx`**

In `resources/js/Pages/Transactions/Cycles/Edit.tsx`, change:

```tsx
    const { data, setData, put, errors } = useForm({
```

to:

```tsx
    const { data, setData, put, errors, processing } = useForm({
```

And change:

```tsx
                    <Button type="submit" disabled={data.items.length === 0} icon={<CheckIcon className="w-4 h-4" />}>Update Cycle</Button>
```

to:

```tsx
                    <Button type="submit" disabled={data.items.length === 0 || processing} icon={<CheckIcon className="w-4 h-4" />}>
                        {processing ? 'Menyimpan...' : 'Update Cycle'}
                    </Button>
```

- [ ] **Step 4: Build to verify no type/syntax errors**

Run: `npm run build`
Expected: build succeeds with no errors mentioning `Create.tsx` or `Edit.tsx`.

- [ ] **Step 5: Manual verification**

Start the dev server (`npm run dev` alongside `php artisan serve`, or however this project is normally run locally), open a Cycle create form, add at least one item, and click "Simpan Cycle" — confirm the button becomes disabled and shows "Menyimpan..." immediately after the click, until the redirect happens. Repeat on the Edit form with "Update Cycle".

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Transactions/Cycles/Create.tsx resources/js/Pages/Transactions/Cycles/Edit.tsx
git commit -m "feat: disable cycle create/edit submit button while request is in flight"
```

---

## Task 6: Double-submit guard — `Cycles/Show.tsx` receive form

**Files:**
- Modify: `resources/js/Pages/Transactions/Cycles/Show.tsx:1,25,93-98,172`

**Interfaces:**
- Consumes: none new.
- Produces: local `submitting` boolean state, following the same pattern as `resources/js/Pages/Transactions/Cycles/QuickReceive.tsx`.

- [ ] **Step 1: Add `submitting` state**

In `resources/js/Pages/Transactions/Cycles/Show.tsx`, change:

```tsx
export default function Show({ cycle, racks, lastUsedRacks }: any) {
    const [isReceiving, setIsReceiving] = useState(false);
```

to:

```tsx
export default function Show({ cycle, racks, lastUsedRacks }: any) {
    const [isReceiving, setIsReceiving] = useState(false);
    const [submitting, setSubmitting] = useState(false);
```

- [ ] **Step 2: Guard `handleReceive` against double submission**

Change:

```tsx
    const handleReceive = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(route('cycles.receive', cycle.id), { items }, {
            onSuccess: () => setIsReceiving(false),
        });
    };
```

to:

```tsx
    const handleReceive = (e: React.FormEvent) => {
        e.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        router.post(route('cycles.receive', cycle.id), { items }, {
            onSuccess: () => setIsReceiving(false),
            onFinish: () => setSubmitting(false),
        });
    };
```

- [ ] **Step 3: Disable the submit button while submitting**

Change:

```tsx
                                <div className="mt-4 flex gap-3">
                                    <Button type="submit">Selesaikan Penerimaan</Button>
                                    <Button type="button" variant="outline" onClick={() => setIsReceiving(false)}>Batal</Button>
                                </div>
```

to:

```tsx
                                <div className="mt-4 flex gap-3">
                                    <Button type="submit" disabled={submitting}>
                                        {submitting ? 'Menyimpan...' : 'Selesaikan Penerimaan'}
                                    </Button>
                                    <Button type="button" variant="outline" onClick={() => setIsReceiving(false)} disabled={submitting}>Batal</Button>
                                </div>
```

- [ ] **Step 4: Build to verify no type/syntax errors**

Run: `npm run build`
Expected: build succeeds with no errors mentioning `Show.tsx` under `Cycles`.

- [ ] **Step 5: Manual verification**

Open a draft Cycle's show page, click "Terima Barang", fill in received quantities and a rack, click "Selesaikan Penerimaan" — confirm the button disables and shows "Menyimpan..." until the page transitions back to non-receiving view.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Transactions/Cycles/Show.tsx
git commit -m "feat: disable cycle receive submit button while request is in flight"
```

---

## Task 7: Double-submit guard — `Shipments/Show.tsx` ship button

**Files:**
- Modify: `resources/js/Pages/Transactions/Shipments/Show.tsx:1,9,16-20,39`

**Interfaces:**
- Consumes: none new.
- Produces: local `submitting` boolean state, same pattern as Task 6.

- [ ] **Step 1: Add `useState` import and `submitting` state**

In `resources/js/Pages/Transactions/Shipments/Show.tsx`, change:

```tsx
import React from 'react';
```

to:

```tsx
import React, { useState } from 'react';
```

and change:

```tsx
export default function Show({ shipment }: any) {
    const statusColors: Record<string, string> = {
```

to:

```tsx
export default function Show({ shipment }: any) {
    const [submitting, setSubmitting] = useState(false);

    const statusColors: Record<string, string> = {
```

- [ ] **Step 2: Guard `handleShip` against double submission**

Change:

```tsx
    const handleShip = () => {
        if (confirm('Proses pengiriman ini? Stok akan dikurangi.')) {
            router.post(route('shipments.ship', shipment.id));
        }
    };
```

to:

```tsx
    const handleShip = () => {
        if (submitting) return;
        if (confirm('Proses pengiriman ini? Stok akan dikurangi.')) {
            setSubmitting(true);
            router.post(route('shipments.ship', shipment.id), {}, {
                onFinish: () => setSubmitting(false),
            });
        }
    };
```

- [ ] **Step 3: Disable the ship button while submitting**

Change:

```tsx
                                <Button variant="outline" size="sm" onClick={handleShip}>Kirim Sekarang</Button></>
```

to:

```tsx
                                <Button variant="outline" size="sm" onClick={handleShip} disabled={submitting}>
                                    {submitting ? 'Memproses...' : 'Kirim Sekarang'}
                                </Button></>
```

- [ ] **Step 4: Build to verify no type/syntax errors**

Run: `npm run build`
Expected: build succeeds with no errors mentioning `Show.tsx` under `Shipments`.

- [ ] **Step 5: Manual verification**

Open a draft Shipment's show page, click "Kirim Sekarang", confirm the dialog — confirm the button disables and shows "Memproses..." until the page redirects to the shipped state.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Transactions/Shipments/Show.tsx
git commit -m "feat: disable shipment ship button while request is in flight"
```
