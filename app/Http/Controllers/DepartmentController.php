<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\Department;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\DepartmentExporter;
use App\Services\ImportExport\Imports\DepartmentImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new DepartmentImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new DepartmentExporter();
    }

    protected function exportFileName(): string
    {
        return 'departments-export';
    }

    public function index(Request $request)
    {
        return Inertia::render('Master/Departments/Index', [
            'departments' => Department::orderBy('name')
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
        return Inertia::render('Master/Departments/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:departments',
        ]);

        Department::create($validated);

        return redirect()->route('departments.index')->with('success', 'Departemen berhasil dibuat.');
    }

    public function edit(Department $department)
    {
        return Inertia::render('Master/Departments/Edit', [
            'department' => $department,
        ]);
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:departments,name,' . $department->id,
        ]);

        $department->update($validated);

        return redirect()->route('departments.index')->with('success', 'Departemen berhasil diupdate.');
    }

    public function destroy(Department $department)
    {
        $department->delete();

        return redirect()->route('departments.index')->with('success', 'Departemen berhasil dihapus.');
    }
}
