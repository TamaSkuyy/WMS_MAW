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
        abort_unless(auth()->user()->can('create job positions'), 403);
        return Inertia::render('Master/JobPositions/Create', [
            'roles' => \Spatie\Permission\Models\Role::pluck('name')->toArray(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('create job positions'), 403);
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
        abort_unless(auth()->user()->can('edit job positions'), 403);
        return Inertia::render('Master/JobPositions/Edit', [
            'position' => $jobPosition,
            'roles' => \Spatie\Permission\Models\Role::pluck('name')->toArray(),
        ]);
    }

    public function update(Request $request, JobPosition $jobPosition)
    {
        abort_unless(auth()->user()->can('edit job positions'), 403);
        $roleNames = JobPosition::roleOptions();
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:job_positions,name,' . $jobPosition->id,
            'level' => 'nullable|string|max:50',
            'role_name' => 'nullable|string|in:' . implode(',', array_merge($roleNames, [''])),
        ]);

        $oldRoleName = $jobPosition->role_name;
        $jobPosition->update($validated);

        // Jika role_name berubah, sync role semua user yang jabatannya ini
        if ($oldRoleName !== $jobPosition->role_name) {
            $employees = \App\Models\Employee::where('job_position_id', $jobPosition->id)
                ->whereNotNull('user_id')
                ->with('user')
                ->get();

            // Hanya role non-admin yang boleh auto-assign dari jabatan
            $allowedRoles = ['operator', 'leader'];

            foreach ($employees as $employee) {
                if (!$employee->user) continue;

                if ($jobPosition->role_name && in_array($jobPosition->role_name, $allowedRoles)) {
                    $employee->user->syncRoles([$jobPosition->role_name]);
                } else {
                    // Role dihapus/diubah ke superadmin → lepas role
                    $employee->user->syncRoles([]);
                }

                // Hapus session user — force re-login biar permission baru kepakai
                \DB::table('sessions')
                    ->where('user_id', $employee->user->id)
                    ->delete();
            }
        }

        return redirect()->route('job-positions.index')->with('success', 'Jabatan berhasil diupdate.');
    }

    public function destroy(JobPosition $jobPosition)
    {
        abort_unless(auth()->user()->can('delete job positions'), 403);
        $jobPosition->delete();

        return redirect()->route('job-positions.index')->with('success', 'Jabatan berhasil dihapus.');
    }
}
