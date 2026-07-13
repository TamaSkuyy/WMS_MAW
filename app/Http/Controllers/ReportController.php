<?php

namespace App\Http\Controllers;

use App\Models\CycleItem;
use App\Models\ShoppingItem;
use App\Models\Supplier;
use App\Services\ImportExport\DTOs\ExportConfig;
use App\Services\ImportExport\Enums\ExportFormat;
use App\Services\ImportExport\Exports\ReceivingReportExporter;
use App\Services\ImportExport\Exports\ShoppingReportExporter;
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

    public function shopping(Request $request)
    {
        $filters = $request->only(['date_from', 'date_to', 'partner', 'status']);

        $items = $this->shoppingQuery($filters)->paginate(15)->withQueryString();

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

        return app(ExportManager::class)->download($exporter, $config);
    }

    private function shoppingQuery(array $filters): Builder
    {
        return ShoppingItem::query()
            ->with(['shopping', 'product', 'rack'])
            ->whereHas('shopping', function ($q) use ($filters) {
                if (! empty($filters['date_from'])) {
                    $q->whereDate('shopping_date', '>=', $filters['date_from']);
                }
                if (! empty($filters['date_to'])) {
                    $q->whereDate('shopping_date', '<=', $filters['date_to']);
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

    private function shoppingSummary(array $filters): array
    {
        $items = $this->shoppingQuery($filters)->get();

        return [
            'total_transactions' => $items->pluck('shopping_id')->unique()->count(),
            'total_quantity' => (int) $items->sum('quantity'),
            'unique_products' => $items->pluck('product_id')->unique()->count(),
        ];
    }
}
