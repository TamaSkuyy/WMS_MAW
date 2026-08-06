<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasImportExport;
use App\Models\User;
use App\Services\ImportExport\Base\BaseExporter;
use App\Services\ImportExport\Base\BaseImporter;
use App\Services\ImportExport\Exports\UserExporter;
use App\Services\ImportExport\Imports\UserImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class UserController extends Controller
{
    use HasImportExport;

    protected function importer(): BaseImporter
    {
        return new UserImporter();
    }

    protected function exporter(): BaseExporter
    {
        return new UserExporter();
    }

    protected function exportFileName(): string
    {
        return 'users-export';
    }

    public function index()
    {
        $users = User::with(['roles', 'employee.jobPosition'])->get();
        $employees = \App\Models\Employee::with('jobPosition')
            ->orderBy('name')
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'label' => $e->name . ($e->jobPosition ? ' — ' . $e->jobPosition->name : ''),
                'role_name' => $e->jobPosition?->role_name,
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('manage users'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', Rules\Password::defaults()],
            'employee_id' => 'nullable|exists:employees,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'employee_id' => $validated['employee_id'] ?? null,
        ]);

        // Auto-assign role from employee's job position
        if (!empty($validated['employee_id'])) {
            $employee = \App\Models\Employee::with('jobPosition')->find($validated['employee_id']);
            if ($employee?->jobPosition?->role_name) {
                $user->syncRoles([$employee->jobPosition->role_name]);
            }
        }

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function update(Request $request, string $id)
    {
        abort_unless(auth()->user()->can('manage users'), 403);

        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class.',id,'.$user->id,
            'password' => ['nullable', Rules\Password::defaults()],
            'employee_id' => 'nullable|exists:employees,id',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->employee_id = $validated['employee_id'] ?? null;
        $user->save();

        // Auto-assign role from employee's job position
        if (!empty($validated['employee_id'])) {
            $employee = \App\Models\Employee::with('jobPosition')->find($validated['employee_id']);
            if ($employee?->jobPosition?->role_name) {
                $user->syncRoles([$employee->jobPosition->role_name]);
            } else {
                $user->syncRoles([]);
            }
        } else {
            $user->syncRoles([]);
        }

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(string $id)
    {
        abort_unless(auth()->user()->can('manage users'), 403);

        $user = User::findOrFail($id);
        if ($user->id === Auth::user()->id) {
            return redirect()->route('users.index')->with('error', 'You cannot delete yourself.');
        }
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    public function export(Request $request)
    {
        if (!Auth::user()?->hasRole('superadmin')) {
            abort(403, 'Only superadmin can export user data.');
        }

        return $this->performExport($request);
    }
}
