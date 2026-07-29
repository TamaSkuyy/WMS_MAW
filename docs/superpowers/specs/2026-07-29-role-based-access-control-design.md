# Design: Role-Based Access Control (Superadmin, Leader, Operator)

> **Date:** 2026-07-29
> **Status:** Approved
> **Context:** Replace existing 4 roles with 3 role system tied to JobPosition

---

## 1. Background

The system currently uses Spatie Laravel Permission with 4 roles: Super Admin, Admin Gudang, Kepala Gudang, Staff Gudang. These roles are assigned manually via the user form and have no connection to the employee's JobPosition.

The request is to simplify to 3 roles (Superadmin, Leader, Operator) and automatically assign roles based on the user's JobPosition. This ensures role consistency and reduces manual assignment errors.

---

## 2. Design

### 2.1 Role Definitions

| Role | Guard | Description | Scope |
|------|-------|-------------|-------|
| `superadmin` | web | Full system access | All modules, all actions |
| `leader` | web | Operational management | View all + CRUD transactions + view reports |
| `operator` | web | Basic operations | View + create transactions, view master data |

### 2.2 Permission Matrix

```
                         superadmin  leader  operator
─────────────────────────────────────────────────────
Dashboard
  view dashboard              ✓         ✓        ✓

Master Data (view all)        ✓         ✓        ✓
Master Data (create/edit/del) ✓         ✗        ✗

Transactions
  view cycles                 ✓         ✓        ✓
  create cycles               ✓         ✓        ✓
  edit cycles                 ✓         ✓        ✗
  delete cycles               ✓         ✓        ✗
  receive cycles              ✓         ✓        ✗
  view stocks                 ✓         ✓        ✓
  view shoppings              ✓         ✓        ✓
  create shoppings            ✓         ✓        ✓
  edit shoppings              ✓         ✓        ✗
  delete shoppings            ✓         ✓        ✗
  ship shoppings              ✓         ✓        ✗

Reports
  view receiving report       ✓         ✓        ✗
  export receiving report     ✓         ✓        ✗
  view shopping report        ✓         ✓        ✗
  export shopping report      ✓         ✓        ✗

System (Setup)
  view users                  ✓         ✗        ✗
  manage users                ✓         ✗        ✗
  view roles                  ✓         ✗        ✗
  manage roles                ✓         ✗        ✗
  view permissions            ✓         ✗        ✗
  manage permissions          ✓         ✗        ✗
  view menus                  ✓         ✗        ✗
  manage menus                ✓         ✗        ✗
```

### 2.3 JobPosition ↔ Role Mapping

Add `role_name` column to `job_positions` table. Each JobPosition stores which Spatie role it maps to.

```php
// job_positions migration
$table->string('role_name')->nullable()->after('level');
```

**Example data:**

| Job Position | role_name |
|-------------|-----------|
| Superadmin | `superadmin` |
| Leader Gudang | `leader` |
| Operator Gudang | `operator` |

### 2.4 Auto-Assignment Flow

```
1. Admin creates/edits a User
2. Admin selects Employee (which has a JobPosition)
3. JobPosition.role_name determines the Spatie role
4. User's role is auto-synced via syncRoles()
```

If User has no Employee, or Employee's JobPosition has no `role_name`, user gets no role assigned (treated as no-access).

### 2.5 Menu Filtering

Existing `HandleInertiaRequests::share()` already filters menus by user permissions. No changes needed — menus auto-hide when user lacks the required permission.

---

## 3. Files to Change

### Database

| File | Action |
|------|--------|
| New migration `xxxx_add_role_name_to_job_positions` | Add `role_name` (nullable string) |
| `database/seeders/WmsRoleSeeder.php` | Replace 4 roles with 3 new roles + permissions |
| `database/seeders/RoleAndMenuSeeder.php` | Update default admin to `superadmin` role |

### Backend

| File | Action |
|------|--------|
| `app/Models/JobPosition.php` | Add `role_name` to `$fillable` |
| `app/Http/Controllers/UserController.php` | Auto-sync role from `employee.jobPosition.role_name` on store/update |
| `app/Http/Controllers/JobPositionController.php` | Add `role_name` validation on store/update |

### Frontend

| File | Action |
|------|--------|
| `resources/js/Pages/Master/JobPositions/Index.tsx` | Show role_name column |
| `resources/js/Pages/Master/JobPositions/Create.tsx` | Add role_name select/dropdown in form |
| `resources/js/Pages/Master/JobPositions/Edit.tsx` | Add role_name select/dropdown in form |
| `resources/js/Pages/Users/Index.tsx` | Show role from employee.jobPosition (read-only) |

### Seeder Cleanup

The following seeders reference old roles and need adjustment:
- `database/seeders/RoleManagementSeeder.php` — references "Super Admin"
- `database/seeders/PermissionManagementSeeder.php` — references "Super Admin"

---

## 4. Edge Cases

1. **User without Employee**: No role assigned. Middleware-based routes return 403.
2. **Employee without JobPosition**: No role assigned. Same behavior.
3. **JobPosition with null role_name**: No role assigned. Same behavior.
4. **User with multiple roles**: Not supported by design. `syncRoles()` ensures single role.
5. **Role name mismatch**: If `role_name` doesn't match any Spatie role, assignRole will throw. Validation on JobPosition form prevents this.
6. **Existing data migration**: Existing users with old roles need manual reassignment, or a migration command.

---

## 5. Non-Goals

- Not changing the permission/menu CRUD UI
- Not changing middleware or route-level authorization patterns
- Not supporting multiple roles per user
- Not auto-creating roles from JobPosition data (roles are seed-based)
