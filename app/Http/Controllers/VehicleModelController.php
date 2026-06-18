<?php
namespace App\Http\Controllers;
use App\Models\VehicleModel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VehicleModelController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Master/VehicleModels/Index', [
            'vehicleModels' => VehicleModel::orderBy('brand')->orderBy('name')
                ->when($request->search, function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%")
                          ->orWhere('brand', 'like', "%{$search}%");
                })
                ->paginate(10)
                ->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }
    public function create()
    {
        return Inertia::render('Master/VehicleModels/Create');
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'brand' => 'required|string|max:100',
        ]);
        VehicleModel::create($validated);
        return redirect()->route('vehicle-models.index')->with('success', 'Model kendaraan berhasil dibuat.');
    }
    public function edit(VehicleModel $vehicleModel)
    {
        return Inertia::render('Master/VehicleModels/Edit', ['vehicleModel' => $vehicleModel]);
    }
    public function update(Request $request, VehicleModel $vehicleModel)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:vehicle_models,name,'.$vehicleModel->id.',id,brand,'.$request->brand,
            'brand' => 'required|string|max:100',
        ]);
        $vehicleModel->update($validated);
        return redirect()->route('vehicle-models.index')->with('success', 'Model kendaraan berhasil diupdate.');
    }
    public function destroy(VehicleModel $vehicleModel)
    {
        $vehicleModel->delete();
        return redirect()->route('vehicle-models.index')->with('success', 'Model kendaraan berhasil dihapus.');
    }
}
