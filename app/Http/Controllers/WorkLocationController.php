<?php

namespace App\Http\Controllers;

use App\Models\WorkLocation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkLocationController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Master/WorkLocations/Index', [
            'locations' => WorkLocation::orderBy('name')
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
        return Inertia::render('Master/WorkLocations/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:work_locations',
        ]);

        WorkLocation::create($validated);

        return redirect()->route('work-locations.index')->with('success', 'Lokasi kerja berhasil dibuat.');
    }

    public function edit(WorkLocation $workLocation)
    {
        return Inertia::render('Master/WorkLocations/Edit', [
            'location' => $workLocation,
        ]);
    }

    public function update(Request $request, WorkLocation $workLocation)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:work_locations,name,' . $workLocation->id,
        ]);

        $workLocation->update($validated);

        return redirect()->route('work-locations.index')->with('success', 'Lokasi kerja berhasil diupdate.');
    }

    public function destroy(WorkLocation $workLocation)
    {
        $workLocation->delete();

        return redirect()->route('work-locations.index')->with('success', 'Lokasi kerja berhasil dihapus.');
    }
}
