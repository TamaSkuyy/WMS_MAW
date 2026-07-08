# Report Transaction — Design Spec

Date: 2026-07-07

## Background

Requirement: "Buatkan Menu Report Transaction untuk rekapitulasi dan pembuatan laporan" — a menu that lets users see a recap of transaction data (receiving and shipment) and generate downloadable reports from it.

The project already has a reusable Import/Export service layer (`app/Services/ImportExport/`) with a `BaseExporter` abstract class supporting Xlsx/CSV/PDF output via `ExportManager`, currently used only by `UserExporter`. This feature reuses that layer rather than building new export machinery.

## Scope

Two separate reports, not one combined report:

- **Receiving Report** — recap of `CycleItem` rows (barang masuk)
- **Shipment Report** — recap of `ShipmentItem` rows (barang keluar)

Each report is its own page with: a filter form, three summary cards computed from the filtered data, a paginated detail table (one row per product per transaction), and Excel/PDF export buttons that export the full filtered dataset (not just the visible page).

Out of scope: CSV export (Excel + PDF only, per requirements gathering), a combined/unified report view, new database tables (all data is derived from existing `Cycle`, `CycleItem`, `Shipment`, `ShipmentItem`, `Product`, `Rack`, `Supplier` tables), and permission-gating beyond the existing `auth` middleware (matches how `Cycles`/`Shipments` pages are currently protected — no extra permission check).

## 1. Menu structure

**Constraint discovered:** `resources/js/Tailadmin/layout/AppSidebar.tsx` only renders two menu levels — a top-level item with `sub_menus`, and each sub-item as a flat link (no grandchild rendering). So "Report Transaction" cannot nest inside the existing "Transactions" parent menu with its own children; it must be its own top-level parent, a sibling of "Transactions".

Added to `database/seeders/MenuSeeder.php`, group `main`:

- **"Report Transaction"** (parent, no `path`, icon `PieChartIcon`, `sort_order` 21 — placed directly after "Transactions" which is 20)
  - **"Receiving Report"** — `path: /reports/receiving`, icon `TableIcon`, `sort_order` 1
  - **"Shipment Report"** — `path: /reports/shipment`, icon `ListIcon`, `sort_order` 2

(`PieChartIcon`, `TableIcon`, `ListIcon` are chosen because they're actually registered in `resources/js/Tailadmin/components/IconMapper.tsx`'s `iconsMap` — several existing menu icon names in `MenuSeeder.php`, e.g. `RefreshIcon`, `BoxIcon`, aren't in that map and silently fall back to a default icon; the new menu items should render correctly rather than repeat that pre-existing mismatch.)

## 2. Routes & controller

New `app/Http/Controllers/ReportController.php`, registered inside the existing `Route::middleware('auth')->group(...)` block in `routes/web.php`:

```
GET /reports/receiving         -> ReportController::receiving        (name: reports.receiving)
GET /reports/receiving/export  -> ReportController::receivingExport   (name: reports.receiving.export)
GET /reports/shipment          -> ReportController::shipment          (name: reports.shipment)
GET /reports/shipment/export   -> ReportController::shipmentExport    (name: reports.shipment.export)
```

## 3. Receiving Report — data & filters

Source: `CycleItem`, eager-loaded with `cycle`, `cycle.supplier`, `product`, `rack`. One row per product per cycle.

**Filters** (all optional, via query string):
- `date_from`, `date_to` — matched against `cycle.received_at`. A cycle that hasn't been received yet has `received_at = null` and is naturally excluded whenever either date bound is set (no special-casing needed — a null column value doesn't satisfy a `whereBetween`/`where >=`/`where <=` clause).
- `supplier_id` — matched against `cycle.supplier_id`. The filter dropdown lists all suppliers (`Supplier::orderBy('name')->get()`), matching the existing convention used in `CycleController::index()`.
- `status` — matched against `cycle.status` (`draft` | `receiving` | `completed`). No default; unset means all statuses included.

**Detail table columns:** Tanggal (`cycle.received_at`, blank if null), No. Cycle (`cycle.cycle_number`), Supplier (`cycle.supplier.name`), Produk (`product.part_number` — `product.name`), Rak (`rack.code`, blank if item hasn't been assigned a rack yet), Qty Diterima (`received_quantity`), Status (`cycle.status`).

Rows for not-yet-received cycles legitimately show `received_quantity = 0` — the Status column makes that self-explanatory, no special empty-state copy needed.

Paginated 15 rows/page, `withQueryString()` (same pattern as `CycleController::index()`).

## 4. Shipment Report — data & filters

Source: `ShipmentItem`, eager-loaded with `shipment`, `product`, `rack`. One row per product per shipment.

**Filters** (all optional, via query string):
- `date_from`, `date_to` — matched against `shipment.shipment_date`.
- `partner` — text search against `shipment.partner_name` via `LIKE '%...%'`, reusing the exact pattern already in `ShipmentController::index()` (`$request->search`). There's no separate Partner model, so this is a free-text filter, not a dropdown.
- `status` — matched against `shipment.status` (`draft` | `shipped`). No default; unset means all statuses included.

**Detail table columns:** Tanggal (`shipment.shipment_date`), Partner (`shipment.partner_name`), Produk (`product.part_number` — `product.name`), Rak (`rack.code`), Qty (`quantity`), Status (`shipment.status`).

Paginated 15 rows/page, `withQueryString()`.

## 5. Summary cards

Both report pages show three cards above the detail table, computed from the same filtered query (before pagination is applied):

- **Total Transaksi** — count of *distinct* `cycle_id` (Receiving) / `shipment_id` (Shipment) among the filtered items, not a count of item rows.
- **Total Qty** — sum of `received_quantity` (Receiving) / `quantity` (Shipment) among the filtered items.
- **Produk Unik** — count of distinct `product_id` among the filtered items.

## 6. Export

Two new exporter classes extending the existing `App\Services\ImportExport\Base\BaseExporter`:

- `app/Services/ImportExport/Exports/ReceivingReportExporter.php`
- `app/Services/ImportExport/Exports/ShipmentReportExporter.php`

Each takes the same filter values used on-screen via its constructor (`array $filters`) and applies them inside `exportQuery()`, so the exported file always matches exactly what the user has filtered for — and always contains the *entire* filtered result set, ignoring the on-screen pagination (matches `BaseExporter`'s existing design: `exportQuery()` has no pagination, `ExportManager::download()` calls `->get()` on the full query).

`ReportController::receivingExport()`/`shipmentExport()` follow the same wiring already used in `UserController::export()`: read `format` from the query string (`ExportFormat::from($request->query('format', 'xlsx'))`), build an `ExportConfig` with the filtered exporter's `headings()`, and delegate to `app(ExportManager::class)->download($exporter, $config)`. No changes needed to `BaseExporter`, `ExportManager`, `ExportConfig`, or the generic `resources/views/exports/table.blade.php` PDF template — all are reused as-is.

Each report page has two export buttons/links (Excel, PDF) that navigate to the export route with the current filter query string plus `format=xlsx` or `format=pdf`, so the browser downloads the file (a plain `<a href>`, not an Inertia visit, since Inertia doesn't handle file download responses).

## 7. Error handling

- `ExportManager::download()` already throws `RuntimeException('No data to export.')` when the filtered query matches zero rows — this bubbles up as a 500 today for `UserController`'s export action, and this feature doesn't change that existing behavior. (Out of scope: improving that error UX — it's a pre-existing gap shared with the current user export feature, not something introduced here.)
- No other new error paths: filters are all optional query-string values with safe defaults (no filter applied), and there's no write/mutation in this feature, only reads.

## 8. Testing

New `tests/Feature/ReportControllerTest.php` (PHPUnit class-based, `RefreshDatabase`, matching existing conventions):

- `reports.receiving` and `reports.shipment` return 200 for an authenticated user.
- Date range filter on each report excludes items outside the range and includes items inside it.
- Supplier filter (Receiving) and partner text-search filter (Shipment) narrow results correctly.
- Status filter narrows results correctly on each report.
- Summary numbers (total transactions, total qty, unique products) match hand-computed expectations for a small filtered fixture set.
- `reports.receiving.export` and `reports.shipment.export` return a successful downloadable response for both `format=xlsx` and `format=pdf`, with filters applied (verified by asserting the response is successful and, where practical, checking `Content-Disposition`/content-type headers — not parsing the binary Xlsx/PDF content itself).
