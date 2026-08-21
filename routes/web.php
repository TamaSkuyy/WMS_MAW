<?php

use App\Http\Controllers\CycleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryMonitorController;
use App\Http\Controllers\DeliverySlotController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ImportStatusController;
use App\Http\Controllers\JobPositionController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RackController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ShoppingController;
use App\Http\Controllers\ShoppingLocationController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPerformanceController;
use App\Http\Controllers\TvDashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleModelController;
use App\Http\Controllers\WorkLocationController;
use App\Notifications\TestRealtimeNotification;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

// ── Public ──────────────────────────────────────────────────
Route::get('/', fn () => redirect()->route('login'));

// Manual Book — static HTML docs (akses: /docs, /docs/, /docs/supplier-management.html)
Route::get('/docs/{file?}', function (?string $file = null) {
    // /docs atau /docs/ → index.html
    if ($file === null || $file === '') {
        return response()->file(public_path('docs/index.html'));
    }
    if (! str_ends_with($file, '.html') || ! file_exists(public_path("docs/{$file}"))) {
        abort(404);
    }
    return response()->file(public_path("docs/{$file}"));
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/tv-dashboard', [TvDashboardController::class, 'index'])->name('tv-dashboard');

// Delivery Monitor — public (TV display, no login required)
Route::get('/delivery-monitor', [DeliveryMonitorController::class, 'index'])->name('delivery-monitor');
Route::get('/delivery-monitor/suppliers/{supplier}/ledger', [DeliveryMonitorController::class, 'ledger'])
    ->name('delivery-monitor.ledger');

// ── Authenticated ───────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // Profile (all authenticated users)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Notifications
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])
        ->name('notifications.markAllRead');
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markAsRead'])
        ->name('notifications.markRead');
    Route::get('/import-status/{importLog}', [ImportStatusController::class, 'show'])
        ->name('import.status');

    // ── Master Data: Suppliers ──────────────────────────────
    // ⚠️ Static routes first to avoid being captured by {supplier}

    // Create (static)
    Route::middleware(PermissionMiddleware::using('create suppliers'))->group(function () {
        Route::get('suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    });

    // Import (pakai create suppliers) / Export (pakai view suppliers)
    // ⚠️ Harus SEBELUM `suppliers/{supplier}` agar tidak tertangkap sebagai route show
    Route::middleware(PermissionMiddleware::using('create suppliers'))->group(function () {
        Route::post('suppliers/import/preview', [SupplierController::class, 'importPreview'])->name('suppliers.import.preview');
        Route::post('suppliers/import', [SupplierController::class, 'import'])->name('suppliers.import');
        Route::get('suppliers/import-template', [SupplierController::class, 'importTemplate'])->name('suppliers.import-template');
    });
    Route::get('suppliers/export', [SupplierController::class, 'export'])
        ->middleware(PermissionMiddleware::using('view suppliers'))->name('suppliers.export');

    // View (parameterized — after static routes)
    Route::middleware(PermissionMiddleware::using('view suppliers'))->group(function () {
        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
    });

    // Edit
    Route::middleware(PermissionMiddleware::using('edit suppliers'))->group(function () {
        Route::get('suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::patch('suppliers/{supplier}', [SupplierController::class, 'update']);
    });

    // Delete
    Route::middleware(PermissionMiddleware::using('delete suppliers'))->group(function () {
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });

    // ── Master Data: Products ───────────────────────────────
    // ⚠️ Static routes first to avoid being captured by {product}
    Route::middleware(PermissionMiddleware::using('create products'))->group(function () {
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::post('products/import/preview', [ProductController::class, 'importPreview'])->name('products.import.preview');
        Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
        Route::get('products/import-template', [ProductController::class, 'importTemplate'])->name('products.import-template');
    });
    // View (parameterized — after static routes)
    Route::middleware(PermissionMiddleware::using('view products'))->group(function () {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
    });

    Route::middleware(PermissionMiddleware::using('edit products'))->group(function () {
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::patch('products/{product}', [ProductController::class, 'update']);
    });
    Route::middleware(PermissionMiddleware::using('delete products'))->group(function () {
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });

    // ── Master Data: Racks ──────────────────────────────────
    // ⚠️ Static routes first to avoid being captured by {rack}
    Route::middleware(PermissionMiddleware::using('create racks'))->group(function () {
        Route::get('racks/create', [RackController::class, 'create'])->name('racks.create');
        Route::post('racks', [RackController::class, 'store'])->name('racks.store');
        Route::post('racks/import/preview', [RackController::class, 'importPreview'])->name('racks.import.preview');
        Route::post('racks/import', [RackController::class, 'import'])->name('racks.import');
        Route::get('racks/import-template', [RackController::class, 'importTemplate'])->name('racks.import-template');
    });
    // View (parameterized — after static routes)
    Route::middleware(PermissionMiddleware::using('view racks'))->group(function () {
        Route::get('racks', [RackController::class, 'index'])->name('racks.index');
        Route::get('racks/{rack}', [RackController::class, 'show'])->name('racks.show');
        Route::get('racks/export', [RackController::class, 'export'])->name('racks.export');
    });

    Route::middleware(PermissionMiddleware::using('edit racks'))->group(function () {
        Route::get('racks/{rack}/edit', [RackController::class, 'edit'])->name('racks.edit');
        Route::put('racks/{rack}', [RackController::class, 'update'])->name('racks.update');
        Route::patch('racks/{rack}', [RackController::class, 'update']);
    });
    Route::middleware(PermissionMiddleware::using('delete racks'))->group(function () {
        Route::delete('racks/{rack}', [RackController::class, 'destroy'])->name('racks.destroy');
    });

    // ── Master Data: Vehicle Models ─────────────────────────
    Route::middleware(PermissionMiddleware::using('view vehicle models'))->group(function () {
        Route::get('vehicle-models', [VehicleModelController::class, 'index'])->name('vehicle-models.index');
        Route::get('vehicle-models/export', [VehicleModelController::class, 'export'])->name('vehicle-models.export');
    });
    Route::middleware(PermissionMiddleware::using('create vehicle models'))->group(function () {
        Route::get('vehicle-models/create', [VehicleModelController::class, 'create'])->name('vehicle-models.create');
        Route::post('vehicle-models', [VehicleModelController::class, 'store'])->name('vehicle-models.store');
        Route::post('vehicle-models/import/preview', [VehicleModelController::class, 'importPreview'])->name('vehicle-models.import.preview');
        Route::post('vehicle-models/import', [VehicleModelController::class, 'import'])->name('vehicle-models.import');
        Route::get('vehicle-models/import-template', [VehicleModelController::class, 'importTemplate'])->name('vehicle-models.import-template');
    });
    Route::middleware(PermissionMiddleware::using('edit vehicle models'))->group(function () {
        Route::get('vehicle-models/{vehicle_model}/edit', [VehicleModelController::class, 'edit'])->name('vehicle-models.edit');
        Route::put('vehicle-models/{vehicle_model}', [VehicleModelController::class, 'update'])->name('vehicle-models.update');
        Route::patch('vehicle-models/{vehicle_model}', [VehicleModelController::class, 'update']);
    });
    Route::middleware(PermissionMiddleware::using('delete vehicle models'))->group(function () {
        Route::delete('vehicle-models/{vehicle_model}', [VehicleModelController::class, 'destroy'])->name('vehicle-models.destroy');
    });

    // ── Master Data: Product Categories ─────────────────────
    Route::middleware(PermissionMiddleware::using('view product categories'))->group(function () {
        Route::get('product-categories', [ProductCategoryController::class, 'index'])->name('product-categories.index');
        Route::get('product-categories/export', [ProductCategoryController::class, 'export'])->name('product-categories.export');
    });
    Route::middleware(PermissionMiddleware::using('create product categories'))->group(function () {
        Route::get('product-categories/create', [ProductCategoryController::class, 'create'])->name('product-categories.create');
        Route::post('product-categories', [ProductCategoryController::class, 'store'])->name('product-categories.store');
        Route::post('product-categories/import/preview', [ProductCategoryController::class, 'importPreview'])->name('product-categories.import.preview');
        Route::post('product-categories/import', [ProductCategoryController::class, 'import'])->name('product-categories.import');
        Route::get('product-categories/import-template', [ProductCategoryController::class, 'importTemplate'])->name('product-categories.import-template');
    });
    Route::middleware(PermissionMiddleware::using('edit product categories'))->group(function () {
        Route::get('product-categories/{product_category}/edit', [ProductCategoryController::class, 'edit'])->name('product-categories.edit');
        Route::put('product-categories/{product_category}', [ProductCategoryController::class, 'update'])->name('product-categories.update');
        Route::patch('product-categories/{product_category}', [ProductCategoryController::class, 'update']);
    });
    Route::middleware(PermissionMiddleware::using('delete product categories'))->group(function () {
        Route::delete('product-categories/{product_category}', [ProductCategoryController::class, 'destroy'])->name('product-categories.destroy');
    });

    // ── Master Data: Job Positions ──────────────────────────
    Route::middleware(PermissionMiddleware::using('view job positions'))->group(function () {
        Route::get('job-positions', [JobPositionController::class, 'index'])->name('job-positions.index');
        Route::get('job-positions/export', [JobPositionController::class, 'export'])->name('job-positions.export');
    });
    Route::middleware(PermissionMiddleware::using('create job positions'))->group(function () {
        Route::get('job-positions/create', [JobPositionController::class, 'create'])->name('job-positions.create');
        Route::post('job-positions', [JobPositionController::class, 'store'])->name('job-positions.store');
        Route::post('job-positions/import/preview', [JobPositionController::class, 'importPreview'])->name('job-positions.import.preview');
        Route::post('job-positions/import', [JobPositionController::class, 'import'])->name('job-positions.import');
        Route::get('job-positions/import-template', [JobPositionController::class, 'importTemplate'])->name('job-positions.import-template');
    });
    Route::middleware(PermissionMiddleware::using('edit job positions'))->group(function () {
        Route::get('job-positions/{job_position}/edit', [JobPositionController::class, 'edit'])->name('job-positions.edit');
        Route::put('job-positions/{job_position}', [JobPositionController::class, 'update'])->name('job-positions.update');
        Route::patch('job-positions/{job_position}', [JobPositionController::class, 'update']);
    });
    Route::middleware(PermissionMiddleware::using('delete job positions'))->group(function () {
        Route::delete('job-positions/{job_position}', [JobPositionController::class, 'destroy'])->name('job-positions.destroy');
    });

    // ── Master Data: Work Locations ─────────────────────────
    Route::middleware(PermissionMiddleware::using('view work locations'))->group(function () {
        Route::get('work-locations', [WorkLocationController::class, 'index'])->name('work-locations.index');
        Route::get('work-locations/export', [WorkLocationController::class, 'export'])->name('work-locations.export');
    });
    Route::middleware(PermissionMiddleware::using('create work locations'))->group(function () {
        Route::get('work-locations/create', [WorkLocationController::class, 'create'])->name('work-locations.create');
        Route::post('work-locations', [WorkLocationController::class, 'store'])->name('work-locations.store');
        Route::post('work-locations/import/preview', [WorkLocationController::class, 'importPreview'])->name('work-locations.import.preview');
        Route::post('work-locations/import', [WorkLocationController::class, 'import'])->name('work-locations.import');
        Route::get('work-locations/import-template', [WorkLocationController::class, 'importTemplate'])->name('work-locations.import-template');
    });
    Route::middleware(PermissionMiddleware::using('edit work locations'))->group(function () {
        Route::get('work-locations/{work_location}/edit', [WorkLocationController::class, 'edit'])->name('work-locations.edit');
        Route::put('work-locations/{work_location}', [WorkLocationController::class, 'update'])->name('work-locations.update');
        Route::patch('work-locations/{work_location}', [WorkLocationController::class, 'update']);
    });
    Route::middleware(PermissionMiddleware::using('delete work locations'))->group(function () {
        Route::delete('work-locations/{work_location}', [WorkLocationController::class, 'destroy'])->name('work-locations.destroy');
    });

    // ── Master Data: Departments ────────────────────────────
    Route::middleware(PermissionMiddleware::using('view departments'))->group(function () {
        Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::get('departments/export', [DepartmentController::class, 'export'])->name('departments.export');
    });
    Route::middleware(PermissionMiddleware::using('create departments'))->group(function () {
        Route::get('departments/create', [DepartmentController::class, 'create'])->name('departments.create');
        Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::post('departments/import/preview', [DepartmentController::class, 'importPreview'])->name('departments.import.preview');
        Route::post('departments/import', [DepartmentController::class, 'import'])->name('departments.import');
        Route::get('departments/import-template', [DepartmentController::class, 'importTemplate'])->name('departments.import-template');
    });
    Route::middleware(PermissionMiddleware::using('edit departments'))->group(function () {
        Route::get('departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit');
        Route::put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::patch('departments/{department}', [DepartmentController::class, 'update']);
    });
    Route::middleware(PermissionMiddleware::using('delete departments'))->group(function () {
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
    });

    // ── Master Data: Employees ──────────────────────────────
    // ⚠️ Static routes first to avoid being captured by {employee}
    Route::middleware(PermissionMiddleware::using('create employees'))->group(function () {
        Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::post('employees/import/preview', [EmployeeController::class, 'importPreview'])->name('employees.import.preview');
        Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import');
        Route::get('employees/import-template', [EmployeeController::class, 'importTemplate'])->name('employees.import-template');
    });
    // View (parameterized — after static routes)
    Route::middleware(PermissionMiddleware::using('view employees'))->group(function () {
        Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        Route::get('employees/export', [EmployeeController::class, 'export'])->name('employees.export');
    });

    Route::middleware(PermissionMiddleware::using('edit employees'))->group(function () {
        Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::patch('employees/{employee}', [EmployeeController::class, 'update']);
        Route::post('employees/{employee}/generate-user', [EmployeeController::class, 'generateUser'])->name('employees.generate-user');
    });
    Route::middleware(PermissionMiddleware::using('delete employees'))->group(function () {
        Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    });

    // ── Master Data: Shifts ─────────────────────────────────
    Route::middleware(PermissionMiddleware::using('view shifts'))->group(function () {
        Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
        Route::get('shifts/export', [ShiftController::class, 'export'])->name('shifts.export');
    });
    Route::middleware(PermissionMiddleware::using('create shifts'))->group(function () {
        Route::get('shifts/create', [ShiftController::class, 'create'])->name('shifts.create');
        Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
        Route::post('shifts/import/preview', [ShiftController::class, 'importPreview'])->name('shifts.import.preview');
        Route::post('shifts/import', [ShiftController::class, 'import'])->name('shifts.import');
        Route::get('shifts/import-template', [ShiftController::class, 'importTemplate'])->name('shifts.import-template');
    });
    Route::middleware(PermissionMiddleware::using('edit shifts'))->group(function () {
        Route::get('shifts/{shift}/edit', [ShiftController::class, 'edit'])->name('shifts.edit');
        Route::put('shifts/{shift}', [ShiftController::class, 'update'])->name('shifts.update');
        Route::patch('shifts/{shift}', [ShiftController::class, 'update']);
    });
    Route::middleware(PermissionMiddleware::using('delete shifts'))->group(function () {
        Route::delete('shifts/{shift}', [ShiftController::class, 'destroy'])->name('shifts.destroy');
    });

    // ── Master Data: Shopping Locations ─────────────────────
    Route::middleware(PermissionMiddleware::using('view shopping locations'))->group(function () {
        Route::get('shopping-locations', [ShoppingLocationController::class, 'index'])->name('shopping-locations.index');
    });
    Route::middleware(PermissionMiddleware::using('create shopping locations'))->group(function () {
        Route::get('shopping-locations/create', [ShoppingLocationController::class, 'create'])->name('shopping-locations.create');
        Route::post('shopping-locations', [ShoppingLocationController::class, 'store'])->name('shopping-locations.store');
    });
    Route::middleware(PermissionMiddleware::using('edit shopping locations'))->group(function () {
        Route::get('shopping-locations/{shopping_location}/edit', [ShoppingLocationController::class, 'edit'])->name('shopping-locations.edit');
        Route::put('shopping-locations/{shopping_location}', [ShoppingLocationController::class, 'update'])->name('shopping-locations.update');
        Route::patch('shopping-locations/{shopping_location}', [ShoppingLocationController::class, 'update']);
    });
    Route::middleware(PermissionMiddleware::using('delete shopping locations'))->group(function () {
        Route::delete('shopping-locations/{shopping_location}', [ShoppingLocationController::class, 'destroy'])->name('shopping-locations.destroy');
    });

    // ── Master Data: Delivery Slots ─────────────────────────
    Route::middleware(PermissionMiddleware::using('view delivery slots'))->group(function () {
        Route::get('delivery-slots', [DeliverySlotController::class, 'index'])->name('delivery-slots.index');
    });
    Route::middleware(PermissionMiddleware::using('edit delivery slots'))->group(function () {
        Route::get('delivery-slots/{delivery_slot}/edit', [DeliverySlotController::class, 'edit'])->name('delivery-slots.edit');
        Route::put('delivery-slots/{delivery_slot}', [DeliverySlotController::class, 'update'])->name('delivery-slots.update');
        Route::patch('delivery-slots/{delivery_slot}', [DeliverySlotController::class, 'update']);
    });

    // ── Transactions: Cycles ────────────────────────────────
    // ⚠️ Static routes MUST be registered BEFORE parameterized
    //    routes (cycles/{cycle}) to prevent "create", "export",
    //    "quick-receive", etc. from being captured as a {cycle} ID.

    // Create (static paths first)
    Route::middleware(PermissionMiddleware::using('create cycles'))->group(function () {
        Route::get('cycles/create', [CycleController::class, 'create'])->name('cycles.create');
        Route::post('cycles', [CycleController::class, 'store'])->name('cycles.store');
        Route::get('cycles/quick-receive', [CycleController::class, 'quickReceiveForm'])->name('cycles.quick-receive.form');
        Route::post('cycles/quick-receive', [CycleController::class, 'quickReceiveStore'])->name('cycles.quick-receive.store');
    });

    // Import (static paths)
    Route::middleware(PermissionMiddleware::using('import cycles'))->group(function () {
        Route::post('cycles/import/preview', [CycleController::class, 'importPreview'])->name('cycles.import.preview');
        Route::post('cycles/import', [CycleController::class, 'import'])->name('cycles.import');
        Route::get('cycles/import-template', [CycleController::class, 'importTemplate'])->name('cycles.import-template');
    });

    // Export (static path)
    Route::middleware(PermissionMiddleware::using('export cycles'))->group(function () {
        Route::get('cycles/export', [CycleController::class, 'export'])->name('cycles.export');
    });

    // View (parameterized — must come AFTER static routes)
    Route::middleware(PermissionMiddleware::using('view cycles'))->group(function () {
        Route::get('cycles', [CycleController::class, 'index'])->name('cycles.index');
        Route::get('cycles/{cycle}', [CycleController::class, 'show'])->name('cycles.show');
    });

    // Edit (parameterized)
    Route::middleware(PermissionMiddleware::using('edit cycles'))->group(function () {
        Route::get('cycles/{cycle}/edit', [CycleController::class, 'edit'])->name('cycles.edit');
        Route::put('cycles/{cycle}', [CycleController::class, 'update'])->name('cycles.update');
        Route::patch('cycles/{cycle}', [CycleController::class, 'update']);
    });

    // Delete (parameterized)
    Route::middleware(PermissionMiddleware::using('delete cycles'))->group(function () {
        Route::delete('cycles/{cycle}', [CycleController::class, 'destroy'])->name('cycles.destroy');
    });

    // Receive (parameterized)
    Route::middleware(PermissionMiddleware::using('receive cycles'))->group(function () {
        Route::post('cycles/{cycle}/receive', [CycleController::class, 'receive'])->name('cycles.receive');
    });

    // ── Transactions: Shopping ──────────────────────────────
    // ⚠️ Static routes MUST be registered BEFORE parameterized
    //    routes (shoppings/{shopping}) to prevent "create" from
    //    being captured as a {shopping} ID.

    // Create (static paths first)
    Route::middleware(PermissionMiddleware::using('create shoppings'))->group(function () {
        Route::get('shoppings/create', [ShoppingController::class, 'create'])->name('shoppings.create');
        Route::post('shoppings', [ShoppingController::class, 'store'])->name('shoppings.store');
        Route::post('shoppings/import/preview', [ShoppingController::class, 'importPreview'])->name('shoppings.import.preview');
        Route::post('shoppings/import', [ShoppingController::class, 'import'])->name('shoppings.import');
        Route::get('shoppings/import-template', [ShoppingController::class, 'importTemplate'])->name('shoppings.import-template');
    });

    // View (parameterized — must come AFTER static routes)
    Route::middleware(PermissionMiddleware::using('view shoppings'))->group(function () {
        Route::get('shoppings', [ShoppingController::class, 'index'])->name('shoppings.index');
        Route::get('shoppings/{shopping}', [ShoppingController::class, 'show'])->name('shoppings.show');
    });

    // Edit (parameterized)
    Route::middleware(PermissionMiddleware::using('edit shoppings'))->group(function () {
        Route::get('shoppings/{shopping}/edit', [ShoppingController::class, 'edit'])->name('shoppings.edit');
        Route::put('shoppings/{shopping}', [ShoppingController::class, 'update'])->name('shoppings.update');
        Route::patch('shoppings/{shopping}', [ShoppingController::class, 'update']);
    });

    // Delete (parameterized)
    Route::middleware(PermissionMiddleware::using('delete shoppings'))->group(function () {
        Route::delete('shoppings/{shopping}', [ShoppingController::class, 'destroy'])->name('shoppings.destroy');
    });

    // Ship (parameterized)
    Route::middleware(PermissionMiddleware::using('ship shoppings'))->group(function () {
        Route::post('shoppings/{shopping}/ship', [ShoppingController::class, 'ship'])->name('shoppings.ship');
    });

    // ── Transactions: Stocks ────────────────────────────────
    Route::middleware(PermissionMiddleware::using('view stocks'))->group(function () {
        Route::resource('stocks', StockController::class)->only(['index']);
    });

    // ── Reports ─────────────────────────────────────────────
    Route::middleware(PermissionMiddleware::using('view receiving report'))->group(function () {
        Route::get('reports/receiving', [ReportController::class, 'receiving'])->name('reports.receiving');
        Route::get('reports/receiving/export', [ReportController::class, 'receivingExport'])->name('reports.receiving.export');
    });

    Route::middleware(PermissionMiddleware::using('view shopping report'))->group(function () {
        Route::get('reports/shopping', [ReportController::class, 'shopping'])->name('reports.shopping');
        Route::get('reports/shopping/export', [ReportController::class, 'shoppingExport'])->name('reports.shopping.export');
    });

    Route::middleware(PermissionMiddleware::using('view supplier performance'))->group(function () {
        Route::get('reports/supplier-performance', [SupplierPerformanceController::class, 'index'])
            ->name('reports.supplier-performance');
        Route::get('reports/supplier-performance/export', [SupplierPerformanceController::class, 'export'])
            ->name('reports.supplier-performance.export');
    });

    // ── System (superadmin only) ────────────────────────────
    Route::middleware(RoleMiddleware::using('superadmin'))->group(function () {

        // Menus
        Route::middleware(PermissionMiddleware::using('view menus'))->group(function () {
            Route::get('menus', [MenuController::class, 'index'])->name('menus.index');
        });
        Route::middleware(PermissionMiddleware::using('manage menus'))->group(function () {
            Route::post('menus', [MenuController::class, 'store'])->name('menus.store');
            Route::put('menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
            Route::patch('menus/{menu}', [MenuController::class, 'update']);
            Route::delete('menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');
        });

        // Users
        Route::middleware(PermissionMiddleware::using('view users'))->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::get('users/export', [UserController::class, 'export'])->name('users.export');
        });
        Route::middleware(PermissionMiddleware::using('manage users'))->group(function () {
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::patch('users/{user}', [UserController::class, 'update']);
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
            Route::post('users/import/preview', [UserController::class, 'importPreview'])->name('users.import.preview');
            Route::post('users/import', [UserController::class, 'import'])->name('users.import');
            Route::get('users/import-template', [UserController::class, 'importTemplate'])->name('users.import-template');
        });

        // Roles
        Route::middleware(PermissionMiddleware::using('view roles'))->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        });
        Route::middleware(PermissionMiddleware::using('manage roles'))->group(function () {
            Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::patch('roles/{role}', [RoleController::class, 'update']);
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        });

        // Permissions
        Route::middleware(PermissionMiddleware::using('view permissions'))->group(function () {
            Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
        });
        Route::middleware(PermissionMiddleware::using('manage permissions'))->group(function () {
            Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store');
            Route::put('permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
            Route::patch('permissions/{permission}', [PermissionController::class, 'update']);
            Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');
        });

        Route::get('/test-broadcast', function () {
            $user = auth()->user();
            if (!$user) return 'Anda harus login dulu!';
            $user->notify(new TestRealtimeNotification);
            return 'Notifikasi realtime telah dikirim!';
        });
    });
});

require __DIR__.'/auth.php';
