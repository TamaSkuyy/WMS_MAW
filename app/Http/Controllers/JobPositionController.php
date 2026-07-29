<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\JobPosition;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\JobPositionExporter;
use App\Services\ImportExport\Imports\JobPositionImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JobPositionController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new JobPositionImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new JobPositionExporter();
    }

    protected function exportFileName(): string
    {
        return 'job-positions-export';
    }

    public function index(Request $request)
    {
        return Inertia::render('Master/JobPositions/Index', [
            'positions' => JobPosition::orderBy('name')
                ->when($request->search, function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%")
                          ->orWhere('level', 'like', "%{$search}%");
                })
                ->paginate(10)
                ->withQueryString(),
            'filters' => $request->only(['search']),
            'roles' => \Spatie\Permission\Models\Role::pluck('name')->toArray(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Master/JobPositions/Create', [
            'roles' => \Spatie\Permission\Models\Role::pluck('name')->toArray(),
        ]);
    }

    public function store(Request $request)
    {
        $roleNames = JobPosition::roleOptions();
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:job_positions',
            'level' => 'nullable|string|max:50',
            'role_name' => 'nullable|string|in:' . implode(',', array_merge($roleNames, [''])),
        ]);

        JobPosition::create($validated);

        return redirect()->route('job-positions.index')->with('success', 'Jabatan berhasil dibuat.');
    }

    public function edit(JobPosition $jobPosition)
    {
        return Inertia::render('Master/JobPositions/Edit', [
            'position' => $jobPosition,
            'roles' => \Spatie\Permission\Models\Role::pluck('name')->toArray(),
        ]);
    }

    public function update(Request $request, JobPosition $jobPosition)
    {
        $roleNames = JobPosition::roleOptions();
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:job_positions,name,' . $jobPosition->id,
            'level' => 'nullable|string|max:50',
            'role_name' => 'nullable|string|in:' . implode(',', array_merge($roleNames, [''])),
        ]);

        $jobPosition->update($validated);

        return redirect()->route('job-positions.index')->with('success', 'Jabatan berhasil diupdate.');
    }

    public function destroy(JobPosition $jobPosition)
    {
        $jobPosition->delete();

        return redirect()->route('job-positions.index')->with('success', 'Jabatan berhasil dihapus.');
    }
}
