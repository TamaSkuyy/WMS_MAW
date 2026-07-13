<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\Shift;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\ShiftExporter;
use App\Services\ImportExport\Imports\ShiftImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShiftController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new ShiftImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new ShiftExporter();
    }

    protected function exportFileName(): string
    {
        return 'shifts-export';
    }

    public function index(Request $request)
    {
        return Inertia::render('Master/Shifts/Index', [
            'shifts' => Shift::orderBy('name')
                ->when($request->search, function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%")
                          ->orWhere('code', 'like', "%{$search}%");
                })
                ->paginate(10)
                ->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Master/Shifts/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:shifts',
            'code' => 'required|string|max:10|unique:shifts',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'status' => 'required|string|in:Aktif,Nonaktif',
        ]);

        $validated['created_by'] = auth()->id();

        Shift::create($validated);

        return redirect()->route('shifts.index')->with('success', 'Shift berhasil dibuat.');
    }

    public function edit(Shift $shift)
    {
        return Inertia::render('Master/Shifts/Edit', [
            'shift' => $shift,
        ]);
    }

    public function update(Request $request, Shift $shift)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:shifts,name,' . $shift->id,
            'code' => 'required|string|max:10|unique:shifts,code,' . $shift->id,
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'status' => 'required|string|in:Aktif,Nonaktif',
        ]);

        $validated['updated_by'] = auth()->id();

        $shift->update($validated);

        return redirect()->route('shifts.index')->with('success', 'Shift berhasil diupdate.');
    }

    public function destroy(Shift $shift)
    {
        $shift->delete();

        return redirect()->route('shifts.index')->with('success', 'Shift berhasil dihapus.');
    }
}
