<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\VehicleModel;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\VehicleModelExporter;
use App\Services\ImportExport\Imports\VehicleModelImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VehicleModelController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new VehicleModelImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new VehicleModelExporter();
    }

    protected function exportFileName(): string
    {
        return 'vehicle-models-export';
    }

    public function index(Request $request)
    {
        return Inertia::render('Master/VehicleModels/Index', [
            'vehicleModels' => VehicleModel::orderBy('name')
                ->when($request->search, function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%");
                })
                ->paginate(10)
                ->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }
    public function create()
    {
        abort_unless(auth()->user()->can('create vehicle models'), 403);
        return Inertia::render('Master/VehicleModels/Create');
    }
    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('create vehicle models'), 403);
        $validated = $request->validate([
            'name'   => 'required|string|max:100',
            'brand'  => 'nullable|string|max:100',
            'suffix' => 'nullable|string|max:50',
        ]);
        $validated['brand'] = $validated['brand'] ?: 'Toyota';
        VehicleModel::create($validated);
        return redirect()->route('vehicle-models.index')->with('success', 'Model kendaraan berhasil dibuat.');
    }
    public function edit(VehicleModel $vehicleModel)
    {
        abort_unless(auth()->user()->can('edit vehicle models'), 403);
        return Inertia::render('Master/VehicleModels/Edit', ['vehicleModel' => $vehicleModel]);
    }
    public function update(Request $request, VehicleModel $vehicleModel)
    {
        abort_unless(auth()->user()->can('edit vehicle models'), 403);
        $validated = $request->validate([
            'name'   => [
                'required', 'string', 'max:100',
                \Illuminate\Validation\Rule::unique('vehicle_models')->where(function ($query) use ($request) {
                    return $query->where('brand', $request->brand ?: 'Toyota')
                                 ->where('suffix', $request->suffix);
                })->ignore($vehicleModel->id),
            ],
            'brand'  => 'nullable|string|max:100',
            'suffix' => 'nullable|string|max:50',
        ]);
        $validated['brand'] = $validated['brand'] ?: 'Toyota';
        $vehicleModel->update($validated);
        return redirect()->route('vehicle-models.index')->with('success', 'Model kendaraan berhasil diupdate.');
    }
    public function destroy(VehicleModel $vehicleModel)
    {
        abort_unless(auth()->user()->can('delete vehicle models'), 403);
        $vehicleModel->delete();
        return redirect()->route('vehicle-models.index')->with('success', 'Model kendaraan berhasil dihapus.');
    }
}
