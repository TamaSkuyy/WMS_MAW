<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\EmployeeExporter;
use App\Services\ImportExport\Imports\EmployeeImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new EmployeeImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new EmployeeExporter();
    }

    protected function exportFileName(): string
    {
        return 'employees-export';
    }

    public function index(Request $request)
    {
        return Inertia::render('Master/Employees/Index', [
            'employees' => Employee::with(['jobPosition', 'workLocation', 'department', 'shift', 'user'])
                ->orderBy('name')
                ->when($request->search, function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%")
                          ->orWhere('nik', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                })
                ->paginate(10)
                ->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        abort_unless(auth()->user()->can('create employees'), 403);
        return Inertia::render('Master/Employees/Create', [
            'jobPositions' => JobPosition::orderBy('name')->get(['id', 'name']),
            'workLocations' => WorkLocation::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'shifts' => Shift::orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')
                ->whereDoesntHave('employee')
                ->orWhereNull('employee_id')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('create employees'), 403);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'nik' => 'nullable|string|max:50|unique:employees',
            'job_position_id' => 'nullable|exists:job_positions,id',
            'work_location_id' => 'nullable|exists:work_locations,id',
            'department_id' => 'nullable|exists:departments,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'user_id' => 'nullable|exists:users,id|unique:employees,user_id',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'status' => 'required|string|in:Aktif,Nonaktif',
            'auto_create_user' => 'nullable|boolean',
        ]);

        $validated['created_by'] = auth()->id();

        // Auto-create user if requested
        if ($request->boolean('auto_create_user') && !empty($validated['email'])) {
            $existingUser = User::where('email', $validated['email'])->first();
            if (!$existingUser) {
                $defaultPassword = \Illuminate\Support\Str::random(12);
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => bcrypt($defaultPassword),
                ]);

                // Auto-assign role from job position
                $jobPosition = JobPosition::find($validated['job_position_id']);
                if ($jobPosition?->role_name) {
                    $this->assignRoleFromJobPosition($user, $jobPosition);
                }

                $validated['user_id'] = $user->id;
                unset($validated['auto_create_user']);
            }
        }

        $employee = Employee::create($validated);

        // Sync role if user already linked
        if ($employee->user_id && $employee->jobPosition?->role_name) {
            $this->assignRoleFromJobPosition($employee->user, $employee->jobPosition);
        }

        return redirect()->route('employees.index')->with('success', 'Karyawan berhasil dibuat.');
    }

    public function show(Employee $employee)
    {
        return Inertia::render('Master/Employees/Show', [
            'employee' => $employee->load(['jobPosition', 'workLocation', 'department', 'shift', 'user', 'creator', 'updater']),
        ]);
    }

    public function edit(Employee $employee)
    {
        abort_unless(auth()->user()->can('edit employees'), 403);
        return Inertia::render('Master/Employees/Edit', [
            'employee' => $employee,
            'jobPositions' => JobPosition::orderBy('name')->get(['id', 'name']),
            'workLocations' => WorkLocation::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'shifts' => Shift::orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')
                ->whereDoesntHave('employee')
                ->orWhere('employee_id', $employee->user_id)
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        abort_unless(auth()->user()->can('edit employees'), 403);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'nik' => 'nullable|string|max:50|unique:employees,nik,' . $employee->id,
            'job_position_id' => 'nullable|exists:job_positions,id',
            'work_location_id' => 'nullable|exists:work_locations,id',
            'department_id' => 'nullable|exists:departments,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'user_id' => 'nullable|exists:users,id|unique:employees,user_id,' . $employee->id,
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'status' => 'required|string|in:Aktif,Nonaktif',
        ]);

        $validated['updated_by'] = auth()->id();

        $employee->update($validated);

        // Sync user role if job position changed and user is linked
        if ($employee->wasChanged('job_position_id') && $employee->user_id && $employee->jobPosition?->role_name) {
            $this->assignRoleFromJobPosition($employee->user, $employee->jobPosition);
        }

        return redirect()->route('employees.index')->with('success', 'Karyawan berhasil diupdate.');
    }

    public function destroy(Employee $employee)
    {
        abort_unless(auth()->user()->can('delete employees'), 403);
        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Karyawan berhasil dihapus.');
    }

    public function generateUser(Employee $employee)
    {
        if ($employee->user_id) {
            return back()->with('error', 'Karyawan ini sudah punya user.');
        }
        if (!$employee->email) {
            return back()->with('error', 'Karyawan belum punya email. Isi email dulu.');
        }
        if (User::where('email', $employee->email)->exists()) {
            return back()->with('error', "Email {$employee->email} sudah dipakai user lain.");
        }

        $user = User::create([
            'name' => $employee->name,
            'email' => $employee->email,
            'password' => bcrypt(\Illuminate\Support\Str::random(32)),
        ]);

        $employee->update(['user_id' => $user->id]);

        if ($employee->jobPosition?->role_name) {
            $this->assignRoleFromJobPosition($user, $employee->jobPosition);
        }

        // Kirim link reset password via email (lebih aman dari plaintext)
        try {
            $token = app('auth.password.broker')->createToken($user);
            $user->sendPasswordResetNotification($token);
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', "User berhasil dibuat untuk {$employee->email}. Link reset password telah dikirim ke email tersebut.");
    }

    private function assignRoleFromJobPosition(User $user, JobPosition $jobPosition): void
    {
        if (!$jobPosition->role_name) return;

        // Hanya role non-admin yang boleh auto-assign dari jabatan
        $allowedRoles = ['operator', 'leader'];
        if (!in_array($jobPosition->role_name, $allowedRoles)) {
            return;
        }

        $user->syncRoles([$jobPosition->role_name]);

        // Hapus session user — force re-login biar permission baru kepakai
        \DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
