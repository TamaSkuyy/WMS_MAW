<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\WorkLocation;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\WorkLocationExporter;
use App\Services\ImportExport\Imports\WorkLocationImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkLocationController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new WorkLocationImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new WorkLocationExporter();
    }

    protected function exportFileName(): string
    {
        return 'work-locations-export';
    }

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
        abort_unless(auth()->user()->can('create work locations'), 403);
        return Inertia::render('Master/WorkLocations/Create');
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('create work locations'), 403);
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:work_locations',
        ]);

        WorkLocation::create($validated);

        return redirect()->route('work-locations.index')->with('success', 'Lokasi kerja berhasil dibuat.');
    }

    public function edit(WorkLocation $workLocation)
    {
        abort_unless(auth()->user()->can('edit work locations'), 403);
        return Inertia::render('Master/WorkLocations/Edit', [
            'location' => $workLocation,
        ]);
    }

    public function update(Request $request, WorkLocation $workLocation)
    {
        abort_unless(auth()->user()->can('edit work locations'), 403);
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:work_locations,name,' . $workLocation->id,
        ]);

        $workLocation->update($validated);

        return redirect()->route('work-locations.index')->with('success', 'Lokasi kerja berhasil diupdate.');
    }

    public function destroy(WorkLocation $workLocation)
    {
        abort_unless(auth()->user()->can('delete work locations'), 403);
        $workLocation->delete();

        return redirect()->route('work-locations.index')->with('success', 'Lokasi kerja berhasil dihapus.');
    }
}
