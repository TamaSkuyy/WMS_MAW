<?php

namespace App\Http\Controllers;

use App\Models\CycleItem;
use App\Models\ShoppingItem;
use App\Models\Supplier;
use App\Services\ImportExport\DTOs\ExportConfig;
use App\Services\ImportExport\Enums\ExportFormat;
use App\Services\ImportExport\Exports\ReceivingReportExporter;
use App\Services\ImportExport\Exports\ShoppingReportExporter;
use App\Services\ImportExport\Exceptions\ExportException;
use App\Services\ImportExport\Managers\ExportManager;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Controllers\Concerns\HasPagination;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    use HasPagination;

    public function receiving(Request $request)
    {
        $filters = $request->only(['date_from', 'date_to', 'supplier_id', 'status']);

        $items = $this->receivingQuery($filters)->paginate($this->perPage(15))->withQueryString();

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

        try {
            return app(ExportManager::class)->download($exporter, $config);
        } catch (ExportException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function receivingQuery(array $filters): Builder
    {
        return $this->receivingFilteredQuery($filters)
            ->with(['cycle.supplier', 'cycle.creator', 'cycle.carrier', 'product', 'rack', 'latestReceiveLog.user'])
            ->latest('id');
    }

    /**
     * Query dasar (tanpa eager load / order) untuk LIST & agregat.
     *
     * Dipisah supaya summary tidak perlu memuat model: dulu `receivingSummary()`
     * memakai query ber-`with()` lalu `->get()` atas SELURUH riwayat cycle_items
     * hanya untuk menghitung 3 angka — penyebab
     * "Allowed memory size of 134217728 bytes exhausted" di BelongsTo saat
     * membuka /reports/receiving tanpa filter tanggal.
     */
    private function receivingFilteredQuery(array $filters): Builder
    {
        return CycleItem::query()
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
            });
    }

    private function receivingSummary(array $filters): array
    {
        // Agregasi di database — jangan pernah ->get() seluruh riwayat di sini.
        return [
            'total_transactions' => (int) $this->receivingFilteredQuery($filters)->distinct()->count('cycle_id'),
            'total_quantity' => (int) $this->receivingFilteredQuery($filters)->sum('received_quantity'),
            'unique_products' => (int) $this->receivingFilteredQuery($filters)->distinct()->count('product_id'),
        ];
    }

    public function shopping(Request $request)
    {
        $filters = $request->only(['date_from', 'date_to', 'partner', 'status']);

        $items = $this->shoppingQuery($filters)->paginate($this->perPage(15))->withQueryString();

        return Inertia::render('Reports/Shopping', [
            'items' => $items,
            'summary' => $this->shoppingSummary($filters),
            'filters' => $filters,
        ]);
    }

    public function shoppingExport(Request $request)
    {
        $filters = $request->only(['date_from', 'date_to', 'partner', 'status']);
        $format = ExportFormat::from($request->query('format', 'xlsx'));

        $exporter = new ShoppingReportExporter($filters);

        $config = new ExportConfig(
            format: $format,
            fileName: 'shopping-report-' . now()->format('Y-m-d-His'),
            headings: $exporter->headings(),
            columns: [],
            exportableClass: ShoppingReportExporter::class,
        );

        try {
            return app(ExportManager::class)->download($exporter, $config);
        } catch (ExportException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function shoppingQuery(array $filters): Builder
    {
        return $this->shoppingFilteredQuery($filters)
            ->with(['shopping.shoppingLocation', 'shopping.shippedBy', 'product', 'rack'])
            ->latest('id');
    }

    /** Lihat catatan pada receivingFilteredQuery() — alasan yang sama. */
    private function shoppingFilteredQuery(array $filters): Builder
    {
        return ShoppingItem::query()
            ->whereHas('shopping', function ($q) use ($filters) {
                if (! empty($filters['date_from'])) {
                    $q->whereDate('shopping_date', '>=', $filters['date_from']);
                }
                if (! empty($filters['date_to'])) {
                    $q->whereDate('shopping_date', '<=', $filters['date_to']);
                }
                if (! empty($filters['partner'])) {
                    $q->whereHas('shoppingLocation', fn ($ql) => $ql->where('name', 'like', '%' . $filters['partner'] . '%'));
                }
                if (! empty($filters['status'])) {
                    $q->where('status', $filters['status']);
                }
            });
    }

    private function shoppingSummary(array $filters): array
    {
        return [
            'total_transactions' => (int) $this->shoppingFilteredQuery($filters)->distinct()->count('shopping_id'),
            'total_quantity' => (int) $this->shoppingFilteredQuery($filters)->sum('quantity'),
            'unique_products' => (int) $this->shoppingFilteredQuery($filters)->distinct()->count('product_id'),
        ];
    }
}
