<?php

namespace App\Http\Controllers;

use App\Models\JobPosition;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JobPositionController extends Controller
{
    public function index()
    {
        return Inertia::render('Master/JobPositions/Index', [
            'positions' => JobPosition::orderBy('name')->paginate(20),
        ]);
    }

    public function create()
    {
        return Inertia::render('Master/JobPositions/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:job_positions',
            'level' => 'nullable|string|max:50',
        ]);

        JobPosition::create($validated);

        return redirect()->route('job-positions.index')->with('success', 'Jabatan berhasil dibuat.');
    }

    public function edit(JobPosition $jobPosition)
    {
        return Inertia::render('Master/JobPositions/Edit', [
            'position' => $jobPosition,
        ]);
    }

    public function update(Request $request, JobPosition $jobPosition)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:job_positions,name,' . $jobPosition->id,
            'level' => 'nullable|string|max:50',
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
