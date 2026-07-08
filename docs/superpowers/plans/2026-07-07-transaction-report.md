# Transaction Report Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a "Report Transaction" menu with two separate report pages — Receiving Report and Shipment Report — each showing filtered summary cards + a paginated detail table, with Excel/PDF export of the full filtered dataset.

**Architecture:** A new `ReportController` builds filtered, per-item queries against the existing `CycleItem`/`ShipmentItem` tables for on-screen display, and two new exporter classes (`ReceivingReportExporter`, `ShipmentReportExporter`) extend the existing `BaseExporter` to reuse the already-built Xlsx/PDF download machinery. A new top-level "Report Transaction" sidebar menu (sibling of "Transactions") holds the two report links, since the sidebar only renders two menu levels.

**Tech Stack:** Laravel 11 (PHP), PHPUnit feature tests, Inertia.js + React (TypeScript), existing `maatwebsite/excel` + `barryvdh/laravel-dompdf` packages via the existing `App\Services\ImportExport` layer.

## Global Constraints

- No new database tables/migrations — all data comes from existing `Cycle`, `CycleItem`, `Shipment`, `ShipmentItem`, `Product`, `Rack`, `Supplier` tables.
- Reuse `App\Services\ImportExport\Base\BaseExporter`, `Managers\ExportManager`, `DTOs\ExportConfig`, `Enums\ExportFormat`, and `resources/views/exports/table.blade.php` exactly as they are — no changes to any of them.
- Export always covers the *entire* filtered result set, not just the current page of the on-screen table.
- Both report routes go inside the existing `Route::middleware('auth')->group(...)` block in `routes/web.php` — same protection level as `Cycles`/`Shipments`, no extra permission gate.
- The sidebar (`resources/js/Tailadmin/layout/AppSidebar.tsx`) only renders two menu levels, so "Report Transaction" must be a new top-level parent menu (sibling of "Transactions"), not nested inside it.
- Use icon names that are actually registered in `resources/js/Tailadmin/components/IconMapper.tsx`'s `iconsMap` (`PieChartIcon`, `TableIcon`, `ListIcon`) rather than repeating the existing mismatched icon names already in `MenuSeeder.php`.
- Follow existing test conventions: PHPUnit class-based tests (`extends Tests\TestCase`, `use RefreshDatabase`), all report tests in one new file `tests/Feature/ReportControllerTest.php`.
- Run backend tests with `php artisan test tests/Feature/<File>.php --filter="..."`. Run frontend verification with `npm run build`.
- Spec reference: `docs/superpowers/specs/2026-07-07-transaction-report-design.md`

---

## File Structure

### Files to Create

```
app/Http/Controllers/ReportController.php                       [receiving/shipment view + export actions]
app/Services/ImportExport/Exports/ReceivingReportExporter.php   [BaseExporter for Receiving Report]
app/Services/ImportExport/Exports/ShipmentReportExporter.php    [BaseExporter for Shipment Report]
resources/js/Pages/Reports/Receiving.tsx                        [Receiving Report page]
resources/js/Pages/Reports/Shipment.tsx                         [Shipment Report page]
tests/Feature/ReportControllerTest.php                          [Tests for both reports]
```

### Files to Modify

```
routes/web.php                        [4 new routes under the auth group]
database/seeders/MenuSeeder.php       [new "Report Transaction" parent + 2 children]
```

---

## Task 1: Receiving Report — backend (controller, exporter, routes)

**Files:**
- Create: `app/Http/Controllers/ReportController.php`
- Create: `app/Services/ImportExport/Exports/ReceivingReportExporter.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/ReportControllerTest.php`

**Interfaces:**
- Produces: `GET /reports/receiving` (name `reports.receiving`) rendering Inertia component `Reports/Receiving` with props `items` (paginated `CycleItem` collection, each eager-loaded with `cycle.supplier`, `product`, `rack`), `summary` (`{ total_transactions: int, total_quantity: int, unique_products: int }`), `filters` (`{ date_from?, date_to?, supplier_id?, status? }`), `suppliers` (all suppliers for the filter dropdown).
- Produces: `GET /reports/receiving/export` (name `reports.receiving.export`) — downloads Xlsx or PDF depending on `?format=`.
- Produces: `App\Services\ImportExport\Exports\ReceivingReportExporter` — constructor `__construct(array $filters = [])`, used by Task 1's controller and reusable as-is by nothing else in this plan.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/ReportControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\CycleItem;
use App\Models\Product;
use App\Models\Rack;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function pageProps($response): array
    {
        $page = json_decode(json_encode($response->viewData('page')), true);

        return $page['props'];
    }

    public function test_receiving_report_returns_200(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.receiving'));

        $response->assertStatus(200);
    }

    public function test_receiving_report_filters_by_date_range(): void
    {
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();

        $inRange = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'received_at' => '2026-06-15 10:00:00',
        ]);
        CycleItem::factory()->create(['cycle_id' => $inRange->id, 'product_id' => $product->id, 'received_quantity' => 5]);

        $outOfRange = Cycle::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'received_at' => '2026-05-01 10:00:00',
        ]);
        CycleItem::factory()->create(['cycle_id' => $outOfRange->id, 'product_id' => $product->id, 'received_quantity' => 7]);

        $response = $this->actingAs($this->user)->get(route('reports.receiving', [
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
        ]));

        $rows = $this->pageProps($response)['items']['data'];

        $this->assertCount(1, $rows);
        $this->assertSame(5, $rows[0]['received_quantity']);
    }

    public function test_receiving_report_filters_by_supplier(): void
    {
        $product = Product::factory()->create();
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();

        $cycleA = Cycle::factory()->create(['supplier_id' => $supplierA->id, 'status' => 'completed', 'received_at' => now()]);
        CycleItem::factory()->create(['cycle_id' => $cycleA->id, 'product_id' => $product->id, 'received_quantity' => 3]);

        $cycleB = Cycle::factory()->create(['supplier_id' => $supplierB->id, 'status' => 'completed', 'received_at' => now()]);
        CycleItem::factory()->create(['cycle_id' => $cycleB->id, 'product_id' => $product->id, 'received_quantity' => 9]);

        $response = $this->actingAs($this->user)->get(route('reports.receiving', ['supplier_id' => $supplierA->id]));

        $rows = $this->pageProps($response)['items']['data'];

        $this->assertCount(1, $rows);
        $this->assertSame(3, $rows[0]['received_quantity']);
    }

    public function test_receiving_report_filters_by_status(): void
    {
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();

        $draftCycle = Cycle::factory()->create(['supplier_id' => $supplier->id, 'status' => 'draft', 'received_at' => null]);
        CycleItem::factory()->create(['cycle_id' => $draftCycle->id, 'product_id' => $product->id, 'received_quantity' => 0]);

        $completedCycle = Cycle::factory()->create(['supplier_id' => $supplier->id, 'status' => 'completed', 'received_at' => now()]);
        CycleItem::factory()->create(['cycle_id' => $completedCycle->id, 'product_id' => $product->id, 'received_quantity' => 4]);

        $response = $this->actingAs($this->user)->get(route('reports.receiving', ['status' => 'completed']));

        $rows = $this->pageProps($response)['items']['data'];

        $this->assertCount(1, $rows);
        $this->assertSame(4, $rows[0]['received_quantity']);
    }

    public function test_receiving_report_summary_totals(): void
    {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        $supplier = Supplier::factory()->create();

        $cycle1 = Cycle::factory()->create(['supplier_id' => $supplier->id, 'status' => 'completed', 'received_at' => now()]);
        CycleItem::factory()->create(['cycle_id' => $cycle1->id, 'product_id' => $productA->id, 'received_quantity' => 5]);
        CycleItem::factory()->create(['cycle_id' => $cycle1->id, 'product_id' => $productB->id, 'received_quantity' => 3]);

        $cycle2 = Cycle::factory()->create(['supplier_id' => $supplier->id, 'status' => 'completed', 'received_at' => now()]);
        CycleItem::factory()->create(['cycle_id' => $cycle2->id, 'product_id' => $productA->id, 'received_quantity' => 2]);

        $response = $this->actingAs($this->user)->get(route('reports.receiving'));

        $summary = $this->pageProps($response)['summary'];

        $this->assertSame(2, $summary['total_transactions']);
        $this->assertSame(10, $summary['total_quantity']);
        $this->assertSame(2, $summary['unique_products']);
    }

    public function test_receiving_report_export_xlsx_returns_success(): void
    {
        $product = Product::factory()->create();
        $cycle = Cycle::factory()->create(['status' => 'completed', 'received_at' => now()]);
        CycleItem::factory()->create(['cycle_id' => $cycle->id, 'product_id' => $product->id, 'received_quantity' => 3]);

        $response = $this->actingAs($this->user)->get(route('reports.receiving.export', ['format' => 'xlsx']));

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_receiving_report_export_pdf_returns_success(): void
    {
        $product = Product::factory()->create();
        $cycle = Cycle::factory()->create(['status' => 'completed', 'received_at' => now()]);
        CycleItem::factory()->create(['cycle_id' => $cycle->id, 'product_id' => $product->id, 'received_quantity' => 3]);

        $response = $this->actingAs($this->user)->get(route('reports.receiving.export', ['format' => 'pdf']));

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
```

Note: `Rack` is intentionally unused in this file so far — Task 3 (Shipment tests) adds a `use App\Models\Rack;` import since `ShipmentItem` requires a `rack_id`. Receiving-side tests above don't need a rack (a `CycleItem`'s `rack_id` is nullable).

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/ReportControllerTest.php`
Expected: FAIL — route `reports.receiving` / `reports.receiving.export` not defined yet.

- [ ] **Step 3: Create the `ReceivingReportExporter`**

Create `app/Services/ImportExport/Exports/ReceivingReportExporter.php`:

```php
<?php

namespace App\Services\ImportExport\Exports;

use App\Models\CycleItem;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class ReceivingReportExporter extends BaseExporter
{
    public function __construct(private array $filters = []) {}

    public function headings(): array
    {
        return ['Tanggal', 'No. Cycle', 'Supplier', 'Part Number', 'Nama Produk', 'Rak', 'Qty Diterima', 'Status'];
    }

    public function exportQuery(): Builder
    {
        $filters = $this->filters;

        return CycleItem::query()
            ->with(['cycle.supplier', 'product', 'rack'])
            ->whereHas('cycle', function ($q) use ($filters) {
                if (! empty($filters['date_from'])) {
                    $q->whereDate('received_at', '>=', $filters['date_from']);
                }
                if (! empty($filters['date_to'])) {
                    $q->whereDate('received_at', '<=', $filters['date_to']);
                }
                if (! empty($filters['supplier_id'])) {
                    $q->where('supplier_id', $filters['supplier_id']);
                }
                if (! empty($filters['status'])) {
                    $q->where('status', $filters['status']);
                }
            })
            ->latest('id');
    }

    public function mapRow($model): array
    {
        return [
            $model->cycle->received_at?->format('Y-m-d') ?? '-',
            $model->cycle->cycle_number,
            $model->cycle->supplier->name ?? '-',
            $model->product->part_number,
            $model->product->name,
            $model->rack->code ?? '-',
            $model->received_quantity,
            $model->cycle->status,
        ];
    }
}
```

- [ ] **Step 4: Create `ReportController` with the Receiving actions**

Create `app/Http/Controllers/ReportController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\CycleItem;
use App\Models\Supplier;
use App\Services\ImportExport\DTOs\ExportConfig;
use App\Services\ImportExport\Enums\ExportFormat;
use App\Services\ImportExport\Exports\ReceivingReportExporter;
use App\Services\ImportExport\Managers\ExportManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function receiving(Request $request)
    {
        $filters = $request->only(['date_from', 'date_to', 'supplier_id', 'status']);

        $items = $this->receivingQuery($filters)->paginate(15)->withQueryString();

        return Inertia::render('Reports/Receiving', [
            'items' => $items,
            'summary' => $this->receivingSummary($filters),
            'filters' => $filters,
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function receivingExport(Request $request)
    {
        $filters = $request->only(['date_from', 'date_to', 'supplier_id', 'status']);
        $format = ExportFormat::from($request->query('format', 'xlsx'));

        $exporter = new ReceivingReportExporter($filters);

        $config = new ExportConfig(
            format: $format,
            fileName: 'receiving-report-' . now()->format('Y-m-d-His'),
            headings: $exporter->headings(),
            columns: [],
            exportableClass: ReceivingReportExporter::class,
        );

        return app(ExportManager::class)->download($exporter, $config);
    }

    private function receivingQuery(array $filters): Builder
    {
        return CycleItem::query()
            ->with(['cycle.supplier', 'product', 'rack'])
            ->whereHas('cycle', function ($q) use ($filters) {
                if (! empty($filters['date_from'])) {
                    $q->whereDate('received_at', '>=', $filters['date_from']);
                }
                if (! empty($filters['date_to'])) {
                    $q->whereDate('received_at', '<=', $filters['date_to']);
                }
                if (! empty($filters['supplier_id'])) {
                    $q->where('supplier_id', $filters['supplier_id']);
                }
                if (! empty($filters['status'])) {
                    $q->where('status', $filters['status']);
                }
            })
            ->latest('id');
    }

    private function receivingSummary(array $filters): array
    {
        $items = $this->receivingQuery($filters)->get();

        return [
            'total_transactions' => $items->pluck('cycle_id')->unique()->count(),
            'total_quantity' => (int) $items->sum('received_quantity'),
            'unique_products' => $items->pluck('product_id')->unique()->count(),
        ];
    }
}
```

- [ ] **Step 5: Add the Receiving routes**

In `routes/web.php`, inside the existing `Route::middleware('auth')->group(function () { ... })` block, add these two lines (near the other Transactions routes, e.g. right after the `stocks` resource route):

```php
    Route::get('reports/receiving', [App\Http\Controllers\ReportController::class, 'receiving'])->name('reports.receiving');
    Route::get('reports/receiving/export', [App\Http\Controllers\ReportController::class, 'receivingExport'])->name('reports.receiving.export');
```

- [ ] **Step 6: Create a placeholder page component so Inertia can resolve it**

Create `resources/js/Pages/Reports/Receiving.tsx` with a minimal placeholder (Task 2 replaces this with the full page):

```tsx
export default function Receiving() {
    return <div>Receiving Report placeholder</div>;
}
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ReportControllerTest.php`
Expected: PASS (all 6 tests written so far)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/ReportController.php app/Services/ImportExport/Exports/ReceivingReportExporter.php routes/web.php resources/js/Pages/Reports/Receiving.tsx tests/Feature/ReportControllerTest.php
git commit -m "feat: add Receiving Report backend with Xlsx/PDF export"
```

---

## Task 2: Receiving Report — frontend page

**Files:**
- Modify (full rewrite of the Task 1 placeholder): `resources/js/Pages/Reports/Receiving.tsx`

**Interfaces:**
- Consumes: the `items`/`summary`/`filters`/`suppliers` props shape produced by `ReportController::receiving()` in Task 1.

- [ ] **Step 1: Write the full Receiving Report page**

Replace the entire contents of `resources/js/Pages/Reports/Receiving.tsx` with:

```tsx
import React from 'react';
import AppLayout from '../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../Tailadmin/components/common/ComponentCard';
import Button from '../../Tailadmin/components/ui/button/Button';
import Input from '../../Tailadmin/components/form/input/InputField';
import SearchableSelect from '../../Tailadmin/components/form/select/SearchableSelect';
import EmptyState from '../../Tailadmin/components/common/EmptyState';

export default function Receiving({ items, summary, filters, suppliers }: any) {
    const updateFilter = (key: string, value: string) => {
        router.get(route('reports.receiving'), { ...filters, [key]: value }, { preserveState: true, replace: true });
    };

    const exportUrl = (format: 'xlsx' | 'pdf') => {
        const params = new URLSearchParams({ ...filters, format }).toString();
        return `${route('reports.receiving.export')}?${params}`;
    };

    const statusColors: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        receiving: 'bg-yellow-100 text-yellow-800',
        completed: 'bg-green-100 text-green-800',
    };

    return (
        <AppLayout>
            <Head title="Receiving Report" />
            <PageBreadcrumb pageTitle="Receiving Report" />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
                <ComponentCard title="Total Transaksi">
                    <div className="text-3xl font-semibold">{summary.total_transactions}</div>
                </ComponentCard>
                <ComponentCard title="Total Qty Diterima">
                    <div className="text-3xl font-semibold">{summary.total_quantity}</div>
                </ComponentCard>
                <ComponentCard title="Produk Unik">
                    <div className="text-3xl font-semibold">{summary.unique_products}</div>
                </ComponentCard>
            </div>

            <ComponentCard title="Detail Transaksi Receiving">
                <div className="mb-4 flex gap-3 flex-wrap items-end">
                    <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Dari Tanggal</label>
                        <Input type="date" defaultValue={filters?.date_from || ''} onChange={(e) => updateFilter('date_from', e.target.value)} />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Sampai Tanggal</label>
                        <Input type="date" defaultValue={filters?.date_to || ''} onChange={(e) => updateFilter('date_to', e.target.value)} />
                    </div>
                    <div className="min-w-[200px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Supplier</label>
                        <SearchableSelect
                            options={[{ value: '', label: 'Semua' }, ...suppliers.map((s: any) => ({ value: s.id, label: s.name }))]}
                            value={filters?.supplier_id || ''}
                            onChange={(v) => updateFilter('supplier_id', v as string)}
                        />
                    </div>
                    <div className="min-w-[160px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <SearchableSelect
                            options={[
                                { value: '', label: 'Semua' },
                                { value: 'draft', label: 'Draft' },
                                { value: 'receiving', label: 'Receiving' },
                                { value: 'completed', label: 'Completed' },
                            ]}
                            value={filters?.status || ''}
                            onChange={(v) => updateFilter('status', v as string)}
                        />
                    </div>
                    <div className="flex gap-2">
                        <a href={exportUrl('xlsx')}><Button variant="outline" size="sm">Export Excel</Button></a>
                        <a href={exportUrl('pdf')}><Button variant="outline" size="sm">Export PDF</Button></a>
                    </div>
                </div>

                {items.data.length === 0 ? (
                    <EmptyState icon="📄" title="Tidak ada data" message="Tidak ada transaksi receiving yang cocok dengan filter ini." />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead className="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Cycle</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rak</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty Diterima</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                {items.data.map((item: any) => (
                                    <tr key={item.id}>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">
                                            {item.cycle?.received_at ? new Date(item.cycle.received_at).toLocaleDateString('id-ID') : '-'}
                                        </td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap font-mono">{item.cycle?.cycle_number}</td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">{item.cycle?.supplier?.name || '-'}</td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">{item.product?.part_number} — {item.product?.name}</td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">{item.rack?.code || '-'}</td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">{item.received_quantity}</td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${statusColors[item.cycle?.status] || ''}`}>
                                                {item.cycle?.status}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {items.total > items.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">Menampilkan {items.from || 0} sampai {items.to || 0} dari {items.total}</div>
                        <div className="flex gap-2">
                            {items.prev_page_url ? (
                                <Link href={items.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Sebelumnya</Link>
                            ) : (
                                <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Sebelumnya</span>
                            )}
                            <span className="px-3 py-1 text-sm">Halaman {items.current_page} dari {items.last_page}</span>
                            {items.next_page_url ? (
                                <Link href={items.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Berikutnya</Link>
                            ) : (
                                <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Berikutnya</span>
                            )}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </AppLayout>
    );
}
```

- [ ] **Step 2: Build to verify no type/syntax errors**

Run: `npm run build`
Expected: build succeeds with no errors mentioning `Reports/Receiving.tsx`.

- [ ] **Step 3: Run the backend report tests again to confirm the real page still satisfies them**

Run: `php artisan test tests/Feature/ReportControllerTest.php`
Expected: PASS

- [ ] **Step 4: Manual verification**

With the local dev server running, log in and open `/reports/receiving`. Confirm: summary cards show numbers, the detail table lists receiving line items, changing the date/supplier/status filters updates the table and URL query string, and both "Export Excel" and "Export PDF" links trigger a file download reflecting the current filters.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Reports/Receiving.tsx
git commit -m "feat: build Receiving Report page with filters and export links"
```

---

## Task 3: Shipment Report — backend (controller, exporter, routes)

**Files:**
- Create: `app/Services/ImportExport/Exports/ShipmentReportExporter.php`
- Modify: `app/Http/Controllers/ReportController.php` (add `shipment()`, `shipmentExport()`, `shipmentQuery()`, `shipmentSummary()`)
- Modify: `routes/web.php`
- Test: `tests/Feature/ReportControllerTest.php`

**Interfaces:**
- Produces: `GET /reports/shipment` (name `reports.shipment`) rendering Inertia component `Reports/Shipment` with props `items` (paginated `ShipmentItem` collection, eager-loaded with `shipment`, `product`, `rack`), `summary` (`{ total_transactions, total_quantity, unique_products }`), `filters` (`{ date_from?, date_to?, partner?, status? }`).
- Produces: `GET /reports/shipment/export` (name `reports.shipment.export`).
- Produces: `App\Services\ImportExport\Exports\ShipmentReportExporter` — constructor `__construct(array $filters = [])`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/ReportControllerTest.php`, just before the final closing `}` of the class. First add `use App\Models\Rack;` and `use App\Models\Shipment;` and `use App\Models\ShipmentItem;` to the top of the file alongside the existing imports:

```php
use App\Models\Rack;
use App\Models\Shipment;
use App\Models\ShipmentItem;
```

Then add the test methods:

```php
    public function test_shipment_report_returns_200(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.shipment'));

        $response->assertStatus(200);
    }

    public function test_shipment_report_filters_by_date_range(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $inRange = Shipment::factory()->create(['status' => 'shipped', 'shipment_date' => '2026-06-15']);
        ShipmentItem::factory()->create(['shipment_id' => $inRange->id, 'product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 6]);

        $outOfRange = Shipment::factory()->create(['status' => 'shipped', 'shipment_date' => '2026-05-01']);
        ShipmentItem::factory()->create(['shipment_id' => $outOfRange->id, 'product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 8]);

        $response = $this->actingAs($this->user)->get(route('reports.shipment', [
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
        ]));

        $rows = $this->pageProps($response)['items']['data'];

        $this->assertCount(1, $rows);
        $this->assertSame(6, $rows[0]['quantity']);
    }

    public function test_shipment_report_filters_by_partner_text_search(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $matching = Shipment::factory()->create(['status' => 'shipped', 'partner_name' => 'PT Maju Jaya']);
        ShipmentItem::factory()->create(['shipment_id' => $matching->id, 'product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 4]);

        $other = Shipment::factory()->create(['status' => 'shipped', 'partner_name' => 'CV Sentosa']);
        ShipmentItem::factory()->create(['shipment_id' => $other->id, 'product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 2]);

        $response = $this->actingAs($this->user)->get(route('reports.shipment', ['partner' => 'Maju']));

        $rows = $this->pageProps($response)['items']['data'];

        $this->assertCount(1, $rows);
        $this->assertSame(4, $rows[0]['quantity']);
    }

    public function test_shipment_report_filters_by_status(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();

        $draft = Shipment::factory()->create(['status' => 'draft']);
        ShipmentItem::factory()->create(['shipment_id' => $draft->id, 'product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 1]);

        $shipped = Shipment::factory()->create(['status' => 'shipped']);
        ShipmentItem::factory()->create(['shipment_id' => $shipped->id, 'product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 5]);

        $response = $this->actingAs($this->user)->get(route('reports.shipment', ['status' => 'shipped']));

        $rows = $this->pageProps($response)['items']['data'];

        $this->assertCount(1, $rows);
        $this->assertSame(5, $rows[0]['quantity']);
    }

    public function test_shipment_report_summary_totals(): void
    {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        $rack = Rack::factory()->create();

        $shipment1 = Shipment::factory()->create(['status' => 'shipped']);
        ShipmentItem::factory()->create(['shipment_id' => $shipment1->id, 'product_id' => $productA->id, 'rack_id' => $rack->id, 'quantity' => 5]);
        ShipmentItem::factory()->create(['shipment_id' => $shipment1->id, 'product_id' => $productB->id, 'rack_id' => $rack->id, 'quantity' => 3]);

        $shipment2 = Shipment::factory()->create(['status' => 'shipped']);
        ShipmentItem::factory()->create(['shipment_id' => $shipment2->id, 'product_id' => $productA->id, 'rack_id' => $rack->id, 'quantity' => 2]);

        $response = $this->actingAs($this->user)->get(route('reports.shipment'));

        $summary = $this->pageProps($response)['summary'];

        $this->assertSame(2, $summary['total_transactions']);
        $this->assertSame(10, $summary['total_quantity']);
        $this->assertSame(2, $summary['unique_products']);
    }

    public function test_shipment_report_export_xlsx_returns_success(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();
        $shipment = Shipment::factory()->create(['status' => 'shipped']);
        ShipmentItem::factory()->create(['shipment_id' => $shipment->id, 'product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 3]);

        $response = $this->actingAs($this->user)->get(route('reports.shipment.export', ['format' => 'xlsx']));

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_shipment_report_export_pdf_returns_success(): void
    {
        $product = Product::factory()->create();
        $rack = Rack::factory()->create();
        $shipment = Shipment::factory()->create(['status' => 'shipped']);
        ShipmentItem::factory()->create(['shipment_id' => $shipment->id, 'product_id' => $product->id, 'rack_id' => $rack->id, 'quantity' => 3]);

        $response = $this->actingAs($this->user)->get(route('reports.shipment.export', ['format' => 'pdf']));

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/pdf');
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/ReportControllerTest.php --filter="test_shipment_report"`
Expected: FAIL — route `reports.shipment` / `reports.shipment.export` not defined yet.

- [ ] **Step 3: Create the `ShipmentReportExporter`**

Create `app/Services/ImportExport/Exports/ShipmentReportExporter.php`:

```php
<?php

namespace App\Services\ImportExport\Exports;

use App\Models\ShipmentItem;
use App\Services\ImportExport\Base\BaseExporter;
use Illuminate\Database\Eloquent\Builder;

class ShipmentReportExporter extends BaseExporter
{
    public function __construct(private array $filters = []) {}

    public function headings(): array
    {
        return ['Tanggal', 'Partner', 'Part Number', 'Nama Produk', 'Rak', 'Qty', 'Status'];
    }

    public function exportQuery(): Builder
    {
        $filters = $this->filters;

        return ShipmentItem::query()
            ->with(['shipment', 'product', 'rack'])
            ->whereHas('shipment', function ($q) use ($filters) {
                if (! empty($filters['date_from'])) {
                    $q->whereDate('shipment_date', '>=', $filters['date_from']);
                }
                if (! empty($filters['date_to'])) {
                    $q->whereDate('shipment_date', '<=', $filters['date_to']);
                }
                if (! empty($filters['partner'])) {
                    $q->where('partner_name', 'like', '%' . $filters['partner'] . '%');
                }
                if (! empty($filters['status'])) {
                    $q->where('status', $filters['status']);
                }
            })
            ->latest('id');
    }

    public function mapRow($model): array
    {
        return [
            $model->shipment->shipment_date->format('Y-m-d'),
            $model->shipment->partner_name,
            $model->product->part_number,
            $model->product->name,
            $model->rack->code,
            $model->quantity,
            $model->shipment->status,
        ];
    }
}
```

- [ ] **Step 4: Add the Shipment actions to `ReportController`**

In `app/Http/Controllers/ReportController.php`, add these imports alongside the existing ones:

```php
use App\Models\ShipmentItem;
use App\Services\ImportExport\Exports\ShipmentReportExporter;
```

Then add these methods inside the `ReportController` class (after the existing `receivingSummary` method):

```php
    public function shipment(Request $request)
    {
        $filters = $request->only(['date_from', 'date_to', 'partner', 'status']);

        $items = $this->shipmentQuery($filters)->paginate(15)->withQueryString();

        return Inertia::render('Reports/Shipment', [
            'items' => $items,
            'summary' => $this->shipmentSummary($filters),
            'filters' => $filters,
        ]);
    }

    public function shipmentExport(Request $request)
    {
        $filters = $request->only(['date_from', 'date_to', 'partner', 'status']);
        $format = ExportFormat::from($request->query('format', 'xlsx'));

        $exporter = new ShipmentReportExporter($filters);

        $config = new ExportConfig(
            format: $format,
            fileName: 'shipment-report-' . now()->format('Y-m-d-His'),
            headings: $exporter->headings(),
            columns: [],
            exportableClass: ShipmentReportExporter::class,
        );

        return app(ExportManager::class)->download($exporter, $config);
    }

    private function shipmentQuery(array $filters): Builder
    {
        return ShipmentItem::query()
            ->with(['shipment', 'product', 'rack'])
            ->whereHas('shipment', function ($q) use ($filters) {
                if (! empty($filters['date_from'])) {
                    $q->whereDate('shipment_date', '>=', $filters['date_from']);
                }
                if (! empty($filters['date_to'])) {
                    $q->whereDate('shipment_date', '<=', $filters['date_to']);
                }
                if (! empty($filters['partner'])) {
                    $q->where('partner_name', 'like', '%' . $filters['partner'] . '%');
                }
                if (! empty($filters['status'])) {
                    $q->where('status', $filters['status']);
                }
            })
            ->latest('id');
    }

    private function shipmentSummary(array $filters): array
    {
        $items = $this->shipmentQuery($filters)->get();

        return [
            'total_transactions' => $items->pluck('shipment_id')->unique()->count(),
            'total_quantity' => (int) $items->sum('quantity'),
            'unique_products' => $items->pluck('product_id')->unique()->count(),
        ];
    }
```

- [ ] **Step 5: Add the Shipment routes**

In `routes/web.php`, right after the two Receiving report routes added in Task 1, add:

```php
    Route::get('reports/shipment', [App\Http\Controllers\ReportController::class, 'shipment'])->name('reports.shipment');
    Route::get('reports/shipment/export', [App\Http\Controllers\ReportController::class, 'shipmentExport'])->name('reports.shipment.export');
```

- [ ] **Step 6: Create a placeholder page component so Inertia can resolve it**

Create `resources/js/Pages/Reports/Shipment.tsx` with a minimal placeholder (Task 4 replaces this with the full page):

```tsx
export default function Shipment() {
    return <div>Shipment Report placeholder</div>;
}
```

- [ ] **Step 7: Run all report tests to verify they pass**

Run: `php artisan test tests/Feature/ReportControllerTest.php`
Expected: PASS (all 12 tests: 6 receiving + 6 shipment)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/ReportController.php app/Services/ImportExport/Exports/ShipmentReportExporter.php routes/web.php resources/js/Pages/Reports/Shipment.tsx tests/Feature/ReportControllerTest.php
git commit -m "feat: add Shipment Report backend with Xlsx/PDF export"
```

---

## Task 4: Shipment Report — frontend page

**Files:**
- Modify (full rewrite of the Task 3 placeholder): `resources/js/Pages/Reports/Shipment.tsx`

**Interfaces:**
- Consumes: the `items`/`summary`/`filters` props shape produced by `ReportController::shipment()` in Task 3.

- [ ] **Step 1: Write the full Shipment Report page**

Replace the entire contents of `resources/js/Pages/Reports/Shipment.tsx` with:

```tsx
import React from 'react';
import AppLayout from '../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../Tailadmin/components/common/ComponentCard';
import Button from '../../Tailadmin/components/ui/button/Button';
import Input from '../../Tailadmin/components/form/input/InputField';
import SearchableSelect from '../../Tailadmin/components/form/select/SearchableSelect';
import EmptyState from '../../Tailadmin/components/common/EmptyState';

export default function Shipment({ items, summary, filters }: any) {
    const updateFilter = (key: string, value: string) => {
        router.get(route('reports.shipment'), { ...filters, [key]: value }, { preserveState: true, replace: true });
    };

    const exportUrl = (format: 'xlsx' | 'pdf') => {
        const params = new URLSearchParams({ ...filters, format }).toString();
        return `${route('reports.shipment.export')}?${params}`;
    };

    const statusColors: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        shipped: 'bg-blue-100 text-blue-800',
        completed: 'bg-green-100 text-green-800',
    };

    return (
        <AppLayout>
            <Head title="Shipment Report" />
            <PageBreadcrumb pageTitle="Shipment Report" />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
                <ComponentCard title="Total Transaksi">
                    <div className="text-3xl font-semibold">{summary.total_transactions}</div>
                </ComponentCard>
                <ComponentCard title="Total Qty Dikirim">
                    <div className="text-3xl font-semibold">{summary.total_quantity}</div>
                </ComponentCard>
                <ComponentCard title="Produk Unik">
                    <div className="text-3xl font-semibold">{summary.unique_products}</div>
                </ComponentCard>
            </div>

            <ComponentCard title="Detail Transaksi Shipment">
                <div className="mb-4 flex gap-3 flex-wrap items-end">
                    <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Dari Tanggal</label>
                        <Input type="date" defaultValue={filters?.date_from || ''} onChange={(e) => updateFilter('date_from', e.target.value)} />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Sampai Tanggal</label>
                        <Input type="date" defaultValue={filters?.date_to || ''} onChange={(e) => updateFilter('date_to', e.target.value)} />
                    </div>
                    <div className="min-w-[200px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Cari Partner</label>
                        <Input type="text" defaultValue={filters?.partner || ''} placeholder="Nama partner..." onChange={(e) => updateFilter('partner', e.target.value)} />
                    </div>
                    <div className="min-w-[160px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <SearchableSelect
                            options={[
                                { value: '', label: 'Semua' },
                                { value: 'draft', label: 'Draft' },
                                { value: 'shipped', label: 'Dikirim' },
                                { value: 'completed', label: 'Completed' },
                            ]}
                            value={filters?.status || ''}
                            onChange={(v) => updateFilter('status', v as string)}
                        />
                    </div>
                    <div className="flex gap-2">
                        <a href={exportUrl('xlsx')}><Button variant="outline" size="sm">Export Excel</Button></a>
                        <a href={exportUrl('pdf')}><Button variant="outline" size="sm">Export PDF</Button></a>
                    </div>
                </div>

                {items.data.length === 0 ? (
                    <EmptyState icon="📄" title="Tidak ada data" message="Tidak ada transaksi shipment yang cocok dengan filter ini." />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead className="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Partner</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produk</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rak</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                {items.data.map((item: any) => (
                                    <tr key={item.id}>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">
                                            {item.shipment?.shipment_date ? new Date(item.shipment.shipment_date).toLocaleDateString('id-ID') : '-'}
                                        </td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">{item.shipment?.partner_name}</td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">{item.product?.part_number} — {item.product?.name}</td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">{item.rack?.code}</td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap">{item.quantity}</td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${statusColors[item.shipment?.status] || ''}`}>
                                                {item.shipment?.status}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {items.total > items.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">Menampilkan {items.from || 0} sampai {items.to || 0} dari {items.total}</div>
                        <div className="flex gap-2">
                            {items.prev_page_url ? (
                                <Link href={items.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Sebelumnya</Link>
                            ) : (
                                <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Sebelumnya</span>
                            )}
                            <span className="px-3 py-1 text-sm">Halaman {items.current_page} dari {items.last_page}</span>
                            {items.next_page_url ? (
                                <Link href={items.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Berikutnya</Link>
                            ) : (
                                <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Berikutnya</span>
                            )}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </AppLayout>
    );
}
```

- [ ] **Step 2: Build to verify no type/syntax errors**

Run: `npm run build`
Expected: build succeeds with no errors mentioning `Reports/Shipment.tsx`.

- [ ] **Step 3: Run the backend report tests again to confirm the real page still satisfies them**

Run: `php artisan test tests/Feature/ReportControllerTest.php`
Expected: PASS (all 12 tests)

- [ ] **Step 4: Manual verification**

Open `/reports/shipment`. Confirm: summary cards show numbers, the detail table lists shipment line items, changing date/partner-search/status filters updates the table and URL query string, and both export links download a file reflecting the current filters.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Reports/Shipment.tsx
git commit -m "feat: build Shipment Report page with filters and export links"
```

---

## Task 5: "Report Transaction" menu

**Files:**
- Modify: `database/seeders/MenuSeeder.php`

**Interfaces:**
- Consumes: `App\Models\Menu` (existing), routes `reports.receiving` and `reports.shipment` from Tasks 1 and 3.

- [ ] **Step 1: Add the menu entries**

In `database/seeders/MenuSeeder.php`, add this block right after the `// === TRANSACTIONS (parent) ===` section closes (i.e., right after the "Shopping" `Menu::create([...])` call and before the `// === OTHERS group ===` comment):

```php
        // === REPORT TRANSACTION (parent) ===
        $reportTransaction = Menu::create([
            'name' => 'Report Transaction',
            'icon' => 'PieChartIcon',
            'sort_order' => 21,
            'group' => 'main',
        ]);

        Menu::create([
            'name' => 'Receiving Report',
            'icon' => 'TableIcon',
            'path' => '/reports/receiving',
            'parent_id' => $reportTransaction->id,
            'sort_order' => 1,
            'group' => 'main',
        ]);

        Menu::create([
            'name' => 'Shipment Report',
            'icon' => 'ListIcon',
            'path' => '/reports/shipment',
            'parent_id' => $reportTransaction->id,
            'sort_order' => 2,
            'group' => 'main',
        ]);
```

- [ ] **Step 2: Re-run the seeder**

Run: `php artisan db:seed --class=MenuSeeder`
Expected: command completes without error (the seeder truncates and rebuilds the `menus` table, so this is safe to re-run).

- [ ] **Step 3: Verify the menu appears**

Run:
```bash
php artisan tinker --execute="echo \App\Models\Menu::where('name', 'Report Transaction')->with('subMenus')->first()->subMenus->pluck('name');"
```
Expected output includes both `"Receiving Report"` and `"Shipment Report"`.

- [ ] **Step 4: Manual verification**

Reload the app in a browser (logged in) and confirm the sidebar now shows a "Report Transaction" group, sitting right below "Transactions", expandable to reveal "Receiving Report" and "Shipment Report" links that navigate to the two new pages.

- [ ] **Step 5: Commit**

```bash
git add database/seeders/MenuSeeder.php
git commit -m "feat: add Report Transaction menu with Receiving/Shipment sub-items"
```
