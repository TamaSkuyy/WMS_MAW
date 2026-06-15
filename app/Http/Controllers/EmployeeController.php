<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index()
    {
        return Inertia::render('Master/Employees/Index', [
            'employees' => Employee::with(['jobPosition', 'workLocation', 'department', 'user'])
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create()
    {
        return Inertia::render('Master/Employees/Create', [
            'jobPositions' => JobPosition::orderBy('name')->get(['id', 'name']),
            'workLocations' => WorkLocation::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')
                ->whereDoesntHave('employee')
                ->orWhereNull('employee_id')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'nik' => 'nullable|string|max:50|unique:employees',
            'job_position_id' => 'nullable|exists:job_positions,id',
            'work_location_id' => 'nullable|exists:work_locations,id',
            'department_id' => 'nullable|exists:departments,id',
            'user_id' => 'nullable|exists:users,id|unique:employees,user_id',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'status' => 'required|string|in:Aktif,Nonaktif',
        ]);

        $validated['created_by'] = auth()->id();

        Employee::create($validated);

        return redirect()->route('employees.index')->with('success', 'Karyawan berhasil dibuat.');
    }

    public function show(Employee $employee)
    {
        return Inertia::render('Master/Employees/Show', [
            'employee' => $employee->load(['jobPosition', 'workLocation', 'department', 'user', 'creator', 'updater']),
        ]);
    }

    public function edit(Employee $employee)
    {
        return Inertia::render('Master/Employees/Edit', [
            'employee' => $employee,
            'jobPositions' => JobPosition::orderBy('name')->get(['id', 'name']),
            'workLocations' => WorkLocation::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')
                ->whereDoesntHave('employee')
                ->orWhere('employee_id', $employee->user_id)
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'nik' => 'nullable|string|max:50|unique:employees,nik,' . $employee->id,
            'job_position_id' => 'nullable|exists:job_positions,id',
            'work_location_id' => 'nullable|exists:work_locations,id',
            'department_id' => 'nullable|exists:departments,id',
            'user_id' => 'nullable|exists:users,id|unique:employees,user_id,' . $employee->id,
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'status' => 'required|string|in:Aktif,Nonaktif',
        ]);

        $validated['updated_by'] = auth()->id();

        $employee->update($validated);

        return redirect()->route('employees.index')->with('success', 'Karyawan berhasil diupdate.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Karyawan berhasil dihapus.');
    }
}
