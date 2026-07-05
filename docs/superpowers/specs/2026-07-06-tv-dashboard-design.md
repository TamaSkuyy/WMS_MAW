# TV Dashboard — Design Spec

Date: 2026-07-06

## Background

The warehouse wants a slideshow-style dashboard meant to run continuously on a TV/kiosk browser for general floor monitoring — a rotating display of active products with a short movement history, refreshed live via Laravel Reverb (already set up in this project for real-time notifications) rather than polling. This is the initial ("for a start") version: broad coverage of all active products, not filtered to alerts or low-stock only.

## Scope

New:
- `app/Http/Controllers/TvDashboardController.php`
- `app/Events/StockChanged.php`
- `resources/js/Pages/TvDashboard/Index.tsx`
- Route: `GET /tv-dashboard` (public, no auth middleware)
- Feature test: `tests/Feature/TvDashboardControllerTest.php`

Modified:
- `routes/web.php` (new public route)
- `app/Http/Controllers/CycleController.php` (`receive()`, `quickReceiveStore()` — fire `StockChanged` after a successful stock mutation)
- `app/Http/Controllers/ShipmentController.php` (`ship()` — fire `StockChanged` after a successful stock mutation)

No new database tables/migrations. No changes to existing authenticated pages or routes.

## 1. Architecture & data flow

- `GET /tv-dashboard` is registered outside the `auth` middleware group in `routes/web.php` — reachable directly by a TV/kiosk browser with no login.
- `TvDashboardController::index()` loads all `is_active = true` products (ordered by name), computes per-product summary data (see section 2), chunks them into groups of 6, and renders `Inertia::render('TvDashboard/Index', ['slides' => $slides])`.
- `App\Events\StockChanged` is a `ShouldBroadcast` event with no payload, broadcasting on a **public** Reverb channel named `warehouse.stock` (no entry needed in `routes/channels.php` since it's public, not private/presence).
- The event is dispatched (`event(new StockChanged())`) immediately after each successful stock-mutating transaction:
  - `CycleController::receive()` — inside the `$ok` truthy branch, after `DB::transaction` returns `true`.
  - `CycleController::quickReceiveStore()` — after the `DB::transaction` closure returns the created `$cycle`.
  - `ShipmentController::ship()` — inside the `$result['ok']` truthy branch, after `DB::transaction` returns.
- On the frontend, `TvDashboard/Index.tsx` subscribes to the public channel via the already-configured `window.Echo` (`window.Echo.channel('warehouse.stock').listen('.StockChanged', handler)`) and, on receipt, triggers an Inertia partial reload of only the `slides` prop (`router.reload({ only: ['slides'], preserveState: true, preserveScroll: true })`) — no full page navigation, so the running slideshow timer and current slide position are undisturbed by the data refresh itself.

## 2. Data per item

For each active product, the controller computes:

- `part_number`, `name`, vehicle model label (brand + name + suffix, matching the existing product display convention used elsewhere in Transactions pages)
- `total_stock`: sum of `Stock.quantity` across all racks for that product (`Stock::where('product_id', $id)->sum('quantity')`)
- `last_received`: the most recent `CycleItem` for that product with `received_quantity > 0`, belonging to a `Cycle` with `status = 'completed'`, giving `{ quantity: received_quantity, date: cycle.received_at }` — `null` if none exists yet
- `last_shipped`: the most recent `ShipmentItem` for that product belonging to a `Shipment` with `status = 'shipped'`, giving `{ quantity, date: shipment.updated_at }` — `null` if none exists yet

Given the current catalog size (~20 active products), these are computed with straightforward per-product queries in a loop rather than a single complex aggregate query — acceptable for this scale; revisit if the catalog grows into the hundreds.

On the frontend, a `null` `last_received`/`last_shipped` renders as "Belum ada data" instead of a blank or zero value.

## 3. Slideshow behavior

- Products are chunked into slides of 6, in the same order as loaded (by product name).
- A client-side `setInterval` (8000ms) advances `currentSlideIndex` by 1, wrapping to `0` after the last slide — continuous, indefinite rotation with no stop condition.
- When a `StockChanged`-triggered partial reload changes the `slides` prop (product added/removed/reordered), `currentSlideIndex` is clamped to `Math.min(currentSlideIndex, slides.length - 1)` so it never points past the end of the refreshed array; the rotation timer itself is not reset.

## 4. Page layout

`TvDashboard/Index.tsx` does **not** use the existing `AppLayout` (sidebar/topbar admin chrome) — it's a standalone full-screen page: no navigation, large typography sized for reading from across a room, styled consistently with the project's existing TailAdmin visual tokens (colors, spacing scale) but at a much larger scale appropriate for TV viewing distance.

## 5. Access

No `auth`/`verified` middleware on `GET /tv-dashboard`. This is an intentional internal-network kiosk page — anyone with the URL can view it, matching the "leave a browser tab open on a TV" use case where a login session could otherwise expire unattended.

## 6. Error handling

- If the Reverb/Echo WebSocket connection drops, the page keeps showing its last successfully loaded `slides` and continues auto-rotating on the client-side timer; `laravel-echo`/`pusher-js` handle reconnection automatically in the background. No user-facing error/retry UI — this is an unattended display with no one present to interact with an error state.
- If the partial reload request itself fails (e.g., transient network blip when refetching after a `StockChanged` signal), the page simply keeps its last-known `slides` prop; the next `StockChanged` event or the next manual page load will pick up current data. No retry loop is added for this initial version.

## 7. Testing

Feature tests in `tests/Feature/TvDashboardControllerTest.php` (PHPUnit class-based, matching this project's existing test convention):

- `GET /tv-dashboard` returns 200 **without** authenticating (`$this->get(route('tv-dashboard'))`, no `actingAs`) — confirms the route is genuinely public.
- Given more than 6 active products, the `slides` prop chunks them into groups of 6 (last group holding the remainder).
- A product with a completed `Cycle`/`CycleItem` receipt shows the correct `last_received` quantity/date; a product with no receipt history shows `null`.
- A product with a shipped `Shipment`/`ShipmentItem` shows the correct `last_shipped` quantity/date; a product with no shipment history shows `null`.

Event-dispatch tests (added to the existing `tests/Feature/CycleControllerTest.php` and `tests/Feature/ShipmentControllerTest.php`, using `Illuminate\Support\Facades\Event::fake()`):

- A successful `CycleController::receive()` call dispatches `App\Events\StockChanged`.
- A successful `CycleController::quickReceiveStore()` call dispatches `App\Events\StockChanged`.
- A successful `ShipmentController::ship()` call dispatches `App\Events\StockChanged`.
- A **rejected** `receive()`/`ship()` call (e.g., second call after the cycle/shipment already left `draft`, or insufficient stock) does **not** dispatch `StockChanged`.

No frontend automated tests are planned for the slideshow rotation/Echo-listener behavior — verified manually by opening the page and observing rotation and live refresh after triggering a receive/ship action, consistent with how frontend double-submit guards were verified in the prior transaction-protection work.
