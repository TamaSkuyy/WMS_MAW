# Role-Based Access Control (Superadmin, Leader, Operator) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace 4 existing Spatie roles with 3 new roles (superadmin, leader, operator) tied to JobPosition via auto-assignment

**Architecture:** Add `role_name` to `job_positions` table. When a user is created/updated with an employee, auto-sync the user's Spatie role from `employee.jobPosition.role_name`. Seeder creates 3 roles with granular permissions. Frontend shows role read-only on users page (derived from employee), and adds role_name dropdown on job position forms.

**Tech Stack:** Laravel 13, Spatie Laravel-Permission, Inertia.js, React/TypeScript, Tailadmin

**Spec:** `docs/superpowers/specs/2026-07-29-role-based-access-control-design.md`

## Global Constraints

- New role names: `superadmin`, `leader`, `operator` (lowercase, guard `web`)
- Old role names to remove: `Super Admin`, `Admin Gudang`, `Kepala Gudang`, `Staff Gudang`
- User can only have ONE role assigned via `syncRoles()`
- Default admin user email: `admin@maw.com`, role: `superadmin`
- Role assignment is automatic from `employee.jobPosition.role_name` — no manual role selection on user form
- JobPosition form validates `role_name` against existing Spatie roles
- User form must read role from employee relation, not allow manual assignment

---

### Task 1: Migration — Add `role_name` to job_positions

**Files:**
- Create: `database/migrations/2026_07_29_000001_add_role_name_to_job_positions.php`

**Interfaces:**
- Produces: `job_positions.role_name` (nullable string, after `level`)

- [ ] **Step 1: Create migration file**

```bash
php artisan make:migration add_role_name_to_job_positions
```

- [ ] **Step 2: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_positions', function (Blueprint $table) {
            $table->string('role_name')->nullable()->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('job_positions', function (Blueprint $table) {
            $table->dropColumn('role_name');
        });
    }
};
```

- [ ] **Step 3: Run migration**

```bash
php artisan migrate
```

Expected: Migration runs without error, `role_name` column added to `job_positions`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_07_29_000001_add_role_name_to_job_positions.php
git commit -m "feat: add role_name column to job_positions table"
```

---

### Task 2: Model — Add `role_name` fillable and relation helper

**Files:**
- Modify: `app/Models/JobPosition.php:13`

**Interfaces:**
- Produces: `JobPosition::$fillable` includes `role_name`
- Produces: `JobPosition::roleOptions()` static method returns array of valid role names

- [ ] **Step 1: Update JobPosition model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobPosition extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'level', 'role_name'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Get all valid role names for dropdown options.
     */
    public static function roleOptions(): array
    {
        return \Spatie\Permission\Models\Role::pluck('name')->toArray();
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Models/JobPosition.php
git commit -m "feat: add role_name to JobPosition fillable and roleOptions helper"
```

---

### Task 3: Seeder — Replace old roles with 3 new roles

**Files:**
- Modify: `database/seeders/WmsRoleSeeder.php`

**Interfaces:**
- Consumes: `job_positions.role_name` from Task 1
- Produces: Roles `superadmin`, `leader`, `operator` with permissions

- [ ] **Step 1: Rewrite WmsRoleSeeder**

Replace the entire file:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class WmsRoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── PERMISSIONS ────────────────────────────────────
        $permissions = [
            // Dashboard
            'view dashboard',

            // Master Data
            'view suppliers', 'create suppliers', 'edit suppliers', 'delete suppliers',
            'view products', 'create products', 'edit products', 'delete products',
            'view racks', 'create racks', 'edit racks', 'delete racks',
            'view vehicle models', 'create vehicle models', 'edit vehicle models', 'delete vehicle models',
            'view product categories', 'create product categories', 'edit product categories', 'delete product categories',
            'view job positions', 'create job positions', 'edit job positions', 'delete job positions',
            'view work locations', 'create work locations', 'edit work locations', 'delete work locations',
            'view departments', 'create departments', 'edit departments', 'delete departments',
            'view employees', 'create employees', 'edit employees', 'delete employees',

            // Transactions
            'view cycles', 'create cycles', 'edit cycles', 'delete cycles', 'receive cycles',
            'view stocks',
            'view shoppings', 'create shoppings', 'edit shoppings', 'delete shoppings', 'ship shoppings',

            // Reports
            'view receiving report', 'export receiving report',
            'view shopping report', 'export shopping report',

            // System
            'view users', 'manage users',
            'view roles', 'manage roles',
            'view permissions', 'manage permissions',
            'view menus', 'manage menus',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // ─── ROLES ──────────────────────────────────────────

        // 1. Superadmin — semua permission
        $superadmin = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $superadmin->syncPermissions(Permission::all());

        // 2. Leader — view all master data + operasional CRUD + reports
        $leader = Role::firstOrCreate(['name' => 'leader', 'guard_name' => 'web']);
        $leader->syncPermissions([
            'view dashboard',

            // View all master data
            'view suppliers', 'view products', 'view racks',
            'view vehicle models', 'view product categories',
            'view job positions', 'view work locations', 'view departments', 'view employees',

            // Transactions (CRUD + approve)
            'view cycles', 'create cycles', 'edit cycles', 'delete cycles', 'receive cycles',
            'view stocks',
            'view shoppings', 'create shoppings', 'edit shoppings', 'delete shoppings', 'ship shoppings',

            // Reports
            'view receiving report', 'export receiving report',
            'view shopping report', 'export shopping report',
        ]);

        // 3. Operator — view + create only, no edit/delete/approve
        $operator = Role::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);
        $operator->syncPermissions([
            'view dashboard',

            // View master data (read-only)
            'view suppliers', 'view products', 'view racks',
            'view vehicle models', 'view product categories',

            // Transactions (create + view only)
            'view cycles', 'create cycles',
            'view stocks',
            'view shoppings', 'create shoppings',
        ]);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add database/seeders/WmsRoleSeeder.php
git commit -m "feat: replace 4 old roles with superadmin/leader/operator with granular permissions"
```

---

### Task 4: Seeder — Update default admin user and cleanup old references

**Files:**
- Modify: `database/seeders/RoleAndMenuSeeder.php`
- Modify: `database/seeders/RoleManagementSeeder.php`
- Modify: `database/seeders/PermissionManagementSeeder.php`

**Interfaces:**
- Consumes: `superadmin` role from Task 3

- [ ] **Step 1: Update RoleAndMenuSeeder**

Change `'Super Admin'` to `'superadmin'` in the role creation and user assignment:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndMenuSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions
        Permission::firstOrCreate(['name' => 'view menus']);
        Permission::firstOrCreate(['name' => 'manage menus']);
        Permission::firstOrCreate(['name' => 'view users']);
        Permission::firstOrCreate(['name' => 'manage users']);
        Permission::firstOrCreate(['name' => 'view dashboard']);

        // assign all permissions to superadmin
        $roleAdmin = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $roleAdmin->givePermissionTo(Permission::all());

        // create default admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@maw.com'],
            [
                'name' => 'Super Admin MAW',
                'password' => Hash::make('password'),
            ]
        );
        $admin->syncRoles(['superadmin']);

        // Menus are handled by MenuSeeder
    }
}
```

- [ ] **Step 2: Update RoleManagementSeeder**

Change `'Super Admin'` to `'superadmin'`:

```php
$roleAdmin = Role::where('name', 'superadmin')->first();
```

- [ ] **Step 3: Update PermissionManagementSeeder**

Change `'Super Admin'` to `'superadmin'`:

```php
$roleAdmin = Role::where('name', 'superadmin')->first();
```

- [ ] **Step 4: Commit**

```bash
git add database/seeders/RoleAndMenuSeeder.php database/seeders/RoleManagementSeeder.php database/seeders/PermissionManagementSeeder.php
git commit -m "feat: update seeders to use superadmin role instead of Super Admin"
```

---

### Task 5: Controller — Add `role_name` to JobPosition validation

**Files:**
- Modify: `app/Http/Controllers/JobPositionController.php:52-57` (store)
- Modify: `app/Http/Controllers/JobPositionController.php:71-76` (update)

**Interfaces:**
- Consumes: `JobPosition::roleOptions()` from Task 2

- [ ] **Step 1: Update store method**

```php
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
```

- [ ] **Step 2: Update update method**

```php
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
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/JobPositionController.php
git commit -m "feat: add role_name validation to JobPosition store and update"
```

---

### Task 6: Controller — Auto-sync role from employee on User store/update

**Files:**
- Modify: `app/Http/Controllers/UserController.php:48-67` (store)
- Modify: `app/Http/Controllers/UserController.php:70-95` (update)
- Modify: `app/Http/Controllers/UserController.php:108-115` (export permission check)

**Interfaces:**
- Consumes: `JobPosition.role_name` via `Employee.jobPosition` relation
- Produces: Auto-assigned role on user save

- [ ] **Step 1: Update store method — remove `role` from user input, derive from employee**

```php
public function store(Request $request)
{
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
```

- [ ] **Step 2: Update update method — same auto-assign logic**

```php
public function update(Request $request, string $id)
{
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
```

- [ ] **Step 3: Fix export permission check — update old hasRole('admin') reference**

Change line 110 from:
```php
if (!Auth::user()?->hasRole('admin')) {
```
to:
```php
if (!Auth::user()?->hasRole('superadmin')) {
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/UserController.php
git commit -m "feat: auto-assign user role from employee job position, remove manual role input"
```

---

### Task 7: Frontend — Add `role_name` to JobPosition Index, Create, Edit

**Files:**
- Modify: `resources/js/Pages/Master/JobPositions/Index.tsx`
- Modify: `resources/js/Pages/Master/JobPositions/Create.tsx`
- Modify: `resources/js/Pages/Master/JobPositions/Edit.tsx`

**Interfaces:**
- Consumes: `role_name` field from JobPosition API
- Consumes: `roles` prop (list of Spatie roles) passed from controller

- [ ] **Step 1: Update JobPositionController index to pass roles**

In `app/Http/Controllers/JobPositionController.php` index method, add roles:

```php
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
```

- [ ] **Step 2: Update JobPositionController create to pass roles**

```php
public function create()
{
    return Inertia::render('Master/JobPositions/Create', [
        'roles' => \Spatie\Permission\Models\Role::pluck('name')->toArray(),
    ]);
}
```

- [ ] **Step 3: Update JobPositionController edit to pass roles**

```php
public function edit(JobPosition $jobPosition)
{
    return Inertia::render('Master/JobPositions/Edit', [
        'position' => $jobPosition,
        'roles' => \Spatie\Permission\Models\Role::pluck('name')->toArray(),
    ]);
}
```

- [ ] **Step 4: Update JobPositions Index — add role_name column**

In `resources/js/Pages/Master/JobPositions/Index.tsx`, after the "Level" `<th>`:

```tsx
<th className="px-4 py-3 text-left text-[11px] font-semibold text-[#6C757D] uppercase tracking-wider">Role</th>
```

And in the table body after the level `<td>`:

```tsx
<td className="px-4 py-3 text-[13px] text-[#6C757D]">
    {p.role_name ? (
        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#EEF2FF] text-[#3B5BDB]">
            {p.role_name}
        </span>
    ) : '-'}
</td>
```

Also add `roles` to the component props:
```tsx
export default function Index({ positions, filters, roles }: any) {
```

And update the ImportModal fields:
```tsx
fields={[
    { key: 'name', label: 'Nama', required: true },
    { key: 'level', label: 'Level', required: false },
    { key: 'role_name', label: 'Role', required: false },
]}
```

- [ ] **Step 5: Update JobPositions Create — add role_name select**

In `resources/js/Pages/Master/JobPositions/Create.tsx`:

```tsx
export default function Create({ roles }: any) {
    const { data, setData, post, errors } = useForm({
        name: '',
        level: '',
        role_name: '',
    });

    const roleOptions = roles.map((r: string) => ({ value: r, label: r }));

    // ... after the Level Select, add:
    <div>
        <Label>Role (Spatie)</Label>
        <Select
            options={roleOptions}
            placeholder="-- Pilih Role --"
            defaultValue={data.role_name}
            onChange={(val) => setData('role_name', val)}
        />
        {errors.role_name && <p className="mt-1 text-sm text-red-500">{errors.role_name}</p>}
    </div>
```

Remove `levelOptions` array since Level field uses text input — keep only the Select for role_name. (The existing Level field is already a Select with `levelOptions`.)

- [ ] **Step 6: Update JobPositions Edit — add role_name select**

In `resources/js/Pages/Master/JobPositions/Edit.tsx`:

```tsx
export default function Edit({ position, roles }: any) {
    const { data, setData, put, errors } = useForm({
        name: position.name,
        level: position.level || '',
        role_name: position.role_name || '',
    });

    const roleOptions = roles.map((r: string) => ({ value: r, label: r }));

    // ... after the Level Select, add:
    <div>
        <Label>Role (Spatie)</Label>
        <Select
            options={roleOptions}
            placeholder="-- Pilih Role --"
            defaultValue={data.role_name}
            onChange={(val) => setData('role_name', val)}
        />
        {errors.role_name && <p className="mt-1 text-sm text-red-500">{errors.role_name}</p>}
    </div>
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/JobPositionController.php resources/js/Pages/Master/JobPositions/
git commit -m "feat: add role_name field to JobPosition forms and table"
```

---

### Task 8: Frontend — Update Users Index to show derived role from employee

**Files:**
- Modify: `resources/js/Pages/Users/Index.tsx`
- Modify: `app/Http/Controllers/UserController.php:37-46` (index)

**Interfaces:**
- Consumes: `employee.jobPosition.role_name` from user relation

- [ ] **Step 1: Update UserController index to eager load employee relation**

```php
public function index()
{
    $users = User::with(['roles', 'employee.jobPosition'])->get();

    return Inertia::render('Users/Index', [
        'users' => $users,
    ]);
}
```

Note: `roles` prop is no longer passed — role selection is now auto-derived.

- [ ] **Step 2: Update Users Index — remove role select, show derived role**

Replace the Role select dropdown in the form (lines 132-145) with:

```tsx
{/* Role is auto-assigned from employee's job position */}
<p className="text-[13px] text-[#6C757D] mt-1">
    Role akan otomatis diisi dari jabatan karyawan yang dipilih.
</p>
```

Update the `useForm` initial data — remove `role`:
```tsx
const { data, setData, post, put, delete: destroy, reset, errors } = useForm({
    name: '',
    email: '',
    password: '',
    employee_id: '',
});
```

Update `handleEdit` — remove `role`:
```tsx
const handleEdit = (user: any) => {
    setIsEditing(true);
    setEditId(user.id);
    setData({
        name: user.name,
        email: user.email,
        password: '',
        employee_id: user.employee_id || '',
    });
};
```

Update the role column in the table to show derived role:
```tsx
<td className="px-4 py-3 whitespace-nowrap text-[13px] text-[#6C757D]">
    {user.employee?.job_position?.role_name ? (
        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#EEF2FF] text-[#3B5BDB]">
            {user.employee.job_position.role_name}
        </span>
    ) : user.roles?.map((r: any) => (
        <span key={r.id} className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#FEF3C7] text-[#B45309]">
            {r.name} (manual)
        </span>
    ))}
</td>
```

Update component to accept props without `roles`:
```tsx
export default function Index({ users }: any) {
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/UserController.php resources/js/Pages/Users/Index.tsx
git commit -m "feat: show user role derived from employee job position, remove manual role selection"
```

---

### Task 9: Refresh database and verify

**Files:**
- None (verification only)

- [ ] **Step 1: Refresh database with new seeders**

```bash
php artisan migrate:fresh --seed
```

Expected: No errors. Tables created. Seeders run successfully.

- [ ] **Step 2: Verify roles exist**

```bash
php artisan tinker --execute="Spatie\Permission\Models\Role::pluck('name')->dump()"
```

Expected output: `['superadmin', 'leader', 'operator']`

- [ ] **Step 3: Verify admin user has superadmin role**

```bash
php artisan tinker --execute="App\Models\User::where('email','admin@maw.com')->first()->getRoleNames()->dump()"
```

Expected output: `['superadmin']`

- [ ] **Step 4: Verify permissions per role**

```bash
php artisan tinker --execute="echo 'superadmin: ' . Spatie\Permission\Models\Role::findByName('superadmin')->permissions->count() . ' permissions'; echo 'leader: ' . Spatie\Permission\Models\Role::findByName('leader')->permissions->count() . ' permissions'; echo 'operator: ' . Spatie\Permission\Models\Role::findByName('operator')->permissions->count() . ' permissions';"
```

- [ ] **Step 5: Verify app loads without errors**

```bash
php artisan serve &
sleep 2
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000
```

Expected: `302` (redirect to login, meaning app is working)

- [ ] **Step 6: Commit (if any fixes applied during verification)**

```bash
git add -A
git commit -m "chore: final verification and fixes for role-based access control"
```
