<?php
namespace App\Http\Controllers;
use App\Models\VehicleModel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VehicleModelController extends Controller
{
    public function index()
    {
        return Inertia::render('Master/VehicleModels/Index', [
            'vehicleModels' => VehicleModel::orderBy('brand')->orderBy('name')->paginate(20),
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
