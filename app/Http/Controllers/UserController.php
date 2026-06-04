<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ImportExport\DTOs\ExportConfig;
use App\Services\ImportExport\Enums\ExportFormat;
use App\Services\ImportExport\Exports\UserExporter;
use App\Services\ImportExport\Imports\UserImporter;
use App\Services\ImportExport\Managers\ExportManager;
use App\Services\ImportExport\Managers\ImportManager;
use App\Services\ImportExport\Models\ImportLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('roles')->get();
        $roles = Role::all();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', Rules\Password::defaults()],
            'role' => 'nullable|string|exists:roles,name',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        if (! empty($validated['role'])) {
            $user->assignRole($validated['role']);
        }

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class.',id,'.$user->id,
            'password' => ['nullable', Rules\Password::defaults()],
            'role' => 'nullable|string|exists:roles,name',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        if (! empty($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        } else {
            $user->syncRoles([]);
        }

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        if ($user->id === Auth::user()->id) {
            return redirect()->route('users.index')->with('error', 'You cannot delete yourself.');
        }
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $manager = app(ImportManager::class);
        $result = $manager->preview(new UserImporter(), $request->file('file'));

        return response()->json($result);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'column_mapping' => 'required|array',
        ]);

        $manager = app(ImportManager::class);
        $importLog = $manager->start(
            new UserImporter(),
            $request->file('file'),
            $request->input('column_mapping'),
            Auth::id(),
        );

        return response()->json([
            'import_log_id' => $importLog->id,
            'status' => $importLog->status,
        ]);
    }

    public function export(Request $request)
    {
        if (!Auth::user()?->hasRole('admin')) {
            abort(403, 'Only administrators can export user data.');
        }

        $format = ExportFormat::from($request->query('format', 'xlsx'));

        $exporter = new UserExporter();

        $config = new ExportConfig(
            format: $format,
            fileName: 'users-export-' . now()->format('Y-m-d-His'),
            headings: $exporter->headings(),
            columns: ['name', 'email', 'role', 'created_at'],
            exportableClass: UserExporter::class,
        );

        $manager = app(ExportManager::class);
        return $manager->download($exporter, $config);
    }

    public function importStatus(ImportLog $importLog)
    {
        if ($importLog->user_id !== Auth::id()) {
            abort(403);
        }

        return response()->json([
            'id' => $importLog->id,
            'status' => $importLog->status,
            'total_rows' => $importLog->total_rows,
            'processed_rows' => $importLog->processed_rows,
            'skipped_rows' => $importLog->skipped_rows,
            'errors' => $importLog->errors,
        ]);
    }
}
