# Shipment → Shopping Full Rename — Design Spec

Date: 2026-07-13

## Background

Requirement: rename the "Shipment" concept to "Shopping" everywhere — not just display text — so the codebase doesn't have a naming split that confuses other programmers. Investigation found this split already exists: `database/seeders/MenuSeeder.php` labels the sidebar menu item "Shopping" while its `path` still points at `/shipments`, and the underlying route, controller, model, database tables, and RBAC permission strings are all still "Shipment". This spec renames the entity completely and consistently across database, backend, RBAC data, tests, frontend, and documentation.

## Scope

Full rename of the Shipment domain entity to Shopping:
- Database tables/columns
- PHP model, controller, exporter, factory classes
- Route names/URIs
- RBAC permission strings (data, not just seeder code)
- `menus` table data (both the pre-existing partial rename and "Shipment Report")
- Backend and frontend tests
- Frontend files/folders, `route()` calls, props, variables, and display text
- `docs/database-documentation.md` and `docs/database-schema.dbml`

Out of scope: renaming the `ship()` controller action/route (`shoppings.ship`) — "ship" is a generic verb describing the dispatch action, not literally the word "Shipment", so it's kept as-is. Historical planning docs under `docs/superpowers/plans/` and `docs/superpowers/specs/` and the old brainstorm artifact under `.superpowers/brainstorm/` are left untouched — they're dated historical records of past decisions, not living documentation.

## Naming map

| Old | New |
|---|---|
| Table `shipments` | `shoppings` |
| Table `shipment_items` | `shopping_items` |
| Column `shipments.shipment_date` | `shoppings.shopping_date` |
| Column `shipment_items.shipment_id` | `shopping_items.shopping_id` |
| Model `App\Models\Shipment` | `App\Models\Shopping` |
| Model `App\Models\ShipmentItem` | `App\Models\ShoppingItem` |
| Controller `ShipmentController` | `ShoppingController` |
| Route names `shipments.*` | `shoppings.*` |
| Route URI `/shipments` | `/shoppings` |
| `ReportController::shipment()` | `shopping()` |
| `ReportController::shipmentExport()` | `shoppingExport()` |
| `ReportController::shipmentQuery()` | `shoppingQuery()` |
| `ReportController::shipmentSummary()` | `shoppingSummary()` |
| Route `reports.shipment` / `reports.shipment.export` | `reports.shopping` / `reports.shopping.export` |
| Route URI `/reports/shipment` | `/reports/shopping` |
| `ShipmentReportExporter` | `ShoppingReportExporter` |
| `ShipmentFactory` / `ShipmentItemFactory` | `ShoppingFactory` / `ShoppingItemFactory` |
| Permission strings `view/create/edit/delete/ship shipments` | `view/create/edit/delete/ship shoppings` |
| Menu "Shopping" → path `/shipments` | path `/shoppings` (fixes the pre-existing half-rename) |
| Menu "Shipment Report" → path `/reports/shipment` | "Shopping Report" → path `/reports/shopping` |
| Frontend folder `Pages/Transactions/Shipments/` | `Pages/Transactions/Shopping/` |
| Frontend file `Pages/Reports/Shipment.tsx` | `Pages/Reports/Shopping.tsx` |
| Test `ShipmentControllerTest.php` | `ShoppingControllerTest.php` |
| Dashboard props `todayShipments`, `pending_shipments` | `todayShoppings`, `pending_shoppings` |
| `lang/id/validation.php` key `'shipment'` | `'shopping'` (value `'pengiriman'` kept — still accurately describes the dispatch action) |

## 1. Database migration

A **new** migration is added (existing migrations that created `shipments`/`shipment_items` are never edited — migration history stays immutable):

```php
public function up(): void
{
    Schema::rename('shipments', 'shoppings');
    Schema::rename('shipment_items', 'shopping_items');

    Schema::table('shoppings', function (Blueprint $table) {
        $table->renameColumn('shipment_date', 'shopping_date');
    });

    Schema::table('shopping_items', function (Blueprint $table) {
        $table->renameColumn('shipment_id', 'shopping_id');
    });
}
```

MySQL 8's native `RENAME COLUMN` (which Laravel's `renameColumn()` uses on this driver) preserves the existing foreign key constraint automatically — no manual drop/re-add of the FK is needed. This will be explicitly verified in testing (§6) since it's the highest-risk step.

Auto-generated index names (e.g. `shipments_status_index`) do **not** get renamed by `Schema::rename()` — they keep their old internal name after the table rename. This is a cosmetic-only MySQL quirk with no functional impact and is accepted as-is (not worth a further migration).

## 2. Backend PHP

- `app/Models/Shipment.php` → `app/Models/Shopping.php`: class renamed to `Shopping`, `$fillable` updated to `['partner_name', 'shopping_date', 'status', 'notes']`, cast key `shopping_date`, relation `items(): HasMany` now points at `ShoppingItem::class`.
- `app/Models/ShipmentItem.php` → `app/Models/ShoppingItem.php`: class renamed to `ShoppingItem`, `$fillable` updated to `['shopping_id', 'product_id', 'rack_id', 'quantity']`, relation `shopping(): BelongsTo` → `Shopping::class` (renamed from `shipment()`).
- `app/Http/Controllers/ShipmentController.php` → `app/Http/Controllers/ShoppingController.php`: class renamed, all `Shipment`/`shipment` type-hints, variables, and `Inertia::render('Transactions/Shipments/...')` calls updated to `Shopping`/`shopping` and `Transactions/Shopping/...`. Method names (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `ship`) are unchanged — they're generic CRUD/action names, not "Shipment"-specific.
- `app/Http/Controllers/DashboardController.php`: `use App\Models\Shopping;`, `Shopping::withCount('items')`, prop keys `todayShoppings` / `pending_shoppings`.
- `app/Http/Controllers/TvDashboardController.php`: `use App\Models\ShoppingItem;`, `ShoppingItem::where(...)->whereHas('shopping', ...)`, `->with('shopping')`, `$lastShippedItem->shopping->updated_at`.
- `app/Http/Controllers/ReportController.php`: `use App\Models\ShoppingItem;`, `use App\Services\ImportExport\Exports\ShoppingReportExporter;`, methods renamed per the naming map, `Inertia::render('Reports/Shopping', ...)`, export filename prefix `shopping-report-`.
- `app/Services/ImportExport/Exports/ShipmentReportExporter.php` → `ShoppingReportExporter.php`: class renamed, queries `ShoppingItem::query()->with(['shopping', 'product', 'rack'])->whereHas('shopping', ...)`, `mapRow()` reads `$model->shopping->shopping_date`.
- `database/factories/ShipmentFactory.php` → `ShoppingFactory.php`, `ShipmentItemFactory.php` → `ShoppingItemFactory.php`: `$model` and field keys updated to match the new column names.

## 3. RBAC and menu data

Both the `permissions` and `menus` tables already contain **live rows** (from seeding), so this is data reconciliation via migration, not just a code edit:

```php
public function up(): void
{
    $renames = [
        'view shipments' => 'view shoppings',
        'create shipments' => 'create shoppings',
        'edit shipments' => 'edit shoppings',
        'delete shipments' => 'delete shoppings',
        'ship shipments' => 'ship shoppings',
    ];

    foreach ($renames as $old => $new) {
        DB::table('permissions')->where('name', $old)->update(['name' => $new]);
    }

    DB::table('menus')->where('path', '/shipments')->update(['path' => '/shoppings']);

    DB::table('menus')->where('name', 'Shipment Report')->update([
        'name' => 'Shopping Report',
        'path' => '/reports/shopping',
    ]);
}
```

Updating `permissions.name` **in place** (rather than delete+recreate) preserves every existing `role_has_permissions`/`model_has_permissions` pivot row, since those reference `permissions.id`, not the name string — no role/user loses their grant.

`database/seeders/WmsRoleSeeder.php` and `database/seeders/MenuSeeder.php` are updated in parallel so fresh installs seed the new names directly (matching the pattern already established for the Shift menu entry: migrations are what reach production, seeders are for fresh/dev installs, and both must stay in sync).

## 4. Tests

- `tests/Feature/ShipmentControllerTest.php` → `tests/Feature/ShoppingControllerTest.php`: all ~51 references updated (model, routes, factories, assertions).
- `tests/Feature/ReportControllerTest.php`: ~37 shipment-related references updated in place (file covers both Receiving and Shopping reports, so it is not renamed).
- `tests/Feature/TvDashboardControllerTest.php`: ~6 references updated in place.
- New assertion added to whichever test covers the migration (or a small dedicated test) verifying the FK constraint survived the rename — e.g. deleting a `Shopping` row still cascades to delete its `ShoppingItem` rows, proving `Schema::rename`/`renameColumn` preserved the constraint.

## 5. Frontend

- `resources/js/Pages/Transactions/Shipments/{Create,Edit,Index,Show}.tsx` → `resources/js/Pages/Transactions/Shopping/{Create,Edit,Index,Show}.tsx`.
- `resources/js/Pages/Reports/Shipment.tsx` → `resources/js/Pages/Reports/Shopping.tsx`.
- Every `route('shipments.*')` / `route('reports.shipment*')` call (18 call sites found across `Dashboard.tsx`, `Reports/Shipment.tsx`, and the four `Transactions/Shipments/*.tsx` files) updated to the new route names.
- Every prop/variable named `shipment`/`shipments`/`shipmentDate` renamed to `shopping`/`shoppings`/`shoppingDate` to match the renamed backend prop keys and model fields.
- All display text ("Shipment" → "Shopping") per the text inventory already gathered (page titles, breadcrumbs, card titles, button labels, empty states, confirm dialogs).
- `resources/js/Pages/Dashboard.tsx` updated to consume the renamed `todayShoppings`/`pending_shoppings` props from `DashboardController`.

## 6. Documentation

- `docs/database-documentation.md`: table names, ER diagram references, and business-flow descriptions updated from `shipments`/`shipment_items` to `shoppings`/`shopping_items`.
- `docs/database-schema.dbml`: `Table shipments {}` / `Table shipment_items {}` renamed to `Table shoppings {}` / `Table shopping_items {}`, including column names and inline comments. Index name references (`idx_shipments_status`, `idx_si_shipment_id`) are updated in the DBML text even though the actual DB index names don't change (§1) — the DBML is documentation describing intended structure, and keeping it internally consistent with the new table names is more valuable than mirroring the harmless old auto-generated index names.

## 7. Testing / verification

- `php artisan migrate` on a fresh test database, then run the full backend suite (`php artisan test`) — confirms the schema rename, column rename, FK survival, RBAC permission update, and menu data update all work together.
- Manually verify (documented as an assistant limitation, same as prior features in this session — no browser automation available): sidebar shows "Shopping" pointing at the correct new URL, Shopping CRUD pages work end-to-end, Shopping Report page/export works, Dashboard's "Shopping Siap Kirim" widget still populates, TV Dashboard's "last shipped" widget still populates, and existing role/user permissions for Shopping actions still function (no accidental permission lockout from the RBAC rename).
- `npm run build` for the frontend.
