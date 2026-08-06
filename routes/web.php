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
    // View
    Route::middleware(PermissionMiddleware::using('view suppliers'))->group(function () {
        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
    });

    // Create
    Route::middleware(PermissionMiddleware::using('create suppliers'))->group(function () {
        Route::get('suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
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

    // Import (pakai create suppliers) / Export (pakai view suppliers)
    Route::middleware(PermissionMiddleware::using('create suppliers'))->group(function () {
        Route::post('suppliers/import/preview', [SupplierController::class, 'importPreview'])->name('suppliers.import.preview');
        Route::post('suppliers/import', [SupplierController::class, 'import'])->name('suppliers.import');
        Route::get('suppliers/import-template', [SupplierController::class, 'importTemplate'])->name('suppliers.import-template');
    });
    Route::get('suppliers/export', [SupplierController::class, 'export'])
        ->middleware(PermissionMiddleware::using('view suppliers'))->name('suppliers.export');

    // ── Master Data: Products ───────────────────────────────
    Route::middleware(PermissionMiddleware::using('view products'))->group(function () {
        Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
        Route::get('products/import-template', [ProductController::class, 'importTemplate'])->name('products.import-template');
        Route::post('products/import/preview', [ProductController::class, 'importPreview'])->name('products.import.preview');
        Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
        Route::resource('products', ProductController::class);
    });

    // ── Master Data: Racks ──────────────────────────────────
    Route::middleware(PermissionMiddleware::using('view racks'))->group(function () {
        Route::get('racks/export', [RackController::class, 'export'])->name('racks.export');
        Route::get('racks/import-template', [RackController::class, 'importTemplate'])->name('racks.import-template');
        Route::post('racks/import/preview', [RackController::class, 'importPreview'])->name('racks.import.preview');
        Route::post('racks/import', [RackController::class, 'import'])->name('racks.import');
        Route::resource('racks', RackController::class);
    });

    // ── Master Data: Vehicle Models ─────────────────────────
    Route::middleware(PermissionMiddleware::using('view vehicle models'))->group(function () {
        Route::get('vehicle-models/export', [VehicleModelController::class, 'export'])->name('vehicle-models.export');
        Route::get('vehicle-models/import-template', [VehicleModelController::class, 'importTemplate'])->name('vehicle-models.import-template');
        Route::post('vehicle-models/import/preview', [VehicleModelController::class, 'importPreview'])->name('vehicle-models.import.preview');
        Route::post('vehicle-models/import', [VehicleModelController::class, 'import'])->name('vehicle-models.import');
        Route::resource('vehicle-models', VehicleModelController::class)->except(['show']);
    });

    // ── Master Data: Product Categories ─────────────────────
    Route::middleware(PermissionMiddleware::using('view product categories'))->group(function () {
        Route::get('product-categories/export', [ProductCategoryController::class, 'export'])->name('product-categories.export');
        Route::get('product-categories/import-template', [ProductCategoryController::class, 'importTemplate'])->name('product-categories.import-template');
        Route::post('product-categories/import/preview', [ProductCategoryController::class, 'importPreview'])->name('product-categories.import.preview');
        Route::post('product-categories/import', [ProductCategoryController::class, 'import'])->name('product-categories.import');
        Route::resource('product-categories', ProductCategoryController::class)->except(['show']);
    });

    // ── Master Data: Job Positions ──────────────────────────
    Route::middleware(PermissionMiddleware::using('view job positions'))->group(function () {
        Route::get('job-positions/export', [JobPositionController::class, 'export'])->name('job-positions.export');
        Route::get('job-positions/import-template', [JobPositionController::class, 'importTemplate'])->name('job-positions.import-template');
        Route::post('job-positions/import/preview', [JobPositionController::class, 'importPreview'])->name('job-positions.import.preview');
        Route::post('job-positions/import', [JobPositionController::class, 'import'])->name('job-positions.import');
        Route::resource('job-positions', JobPositionController::class)->except(['show']);
    });

    // ── Master Data: Work Locations ─────────────────────────
    Route::middleware(PermissionMiddleware::using('view work locations'))->group(function () {
        Route::get('work-locations/export', [WorkLocationController::class, 'export'])->name('work-locations.export');
        Route::get('work-locations/import-template', [WorkLocationController::class, 'importTemplate'])->name('work-locations.import-template');
        Route::post('work-locations/import/preview', [WorkLocationController::class, 'importPreview'])->name('work-locations.import.preview');
        Route::post('work-locations/import', [WorkLocationController::class, 'import'])->name('work-locations.import');
        Route::resource('work-locations', WorkLocationController::class)->except(['show']);
    });

    // ── Master Data: Departments ────────────────────────────
    Route::middleware(PermissionMiddleware::using('view departments'))->group(function () {
        Route::get('departments/export', [DepartmentController::class, 'export'])->name('departments.export');
        Route::get('departments/import-template', [DepartmentController::class, 'importTemplate'])->name('departments.import-template');
        Route::post('departments/import/preview', [DepartmentController::class, 'importPreview'])->name('departments.import.preview');
        Route::post('departments/import', [DepartmentController::class, 'import'])->name('departments.import');
        Route::resource('departments', DepartmentController::class)->except(['show']);
    });

    // ── Master Data: Employees ──────────────────────────────
    Route::middleware(PermissionMiddleware::using('view employees'))->group(function () {
        Route::get('employees/export', [EmployeeController::class, 'export'])->name('employees.export');
        Route::get('employees/import-template', [EmployeeController::class, 'importTemplate'])->name('employees.import-template');
        Route::post('employees/import/preview', [EmployeeController::class, 'importPreview'])->name('employees.import.preview');
        Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import');
        Route::post('employees/{employee}/generate-user', [EmployeeController::class, 'generateUser'])->name('employees.generate-user');
        Route::resource('employees', EmployeeController::class);
    });

    // ── Master Data: Shifts ─────────────────────────────────
    Route::middleware(PermissionMiddleware::using('view shifts'))->group(function () {
        Route::get('shifts/export', [ShiftController::class, 'export'])->name('shifts.export');
        Route::get('shifts/import-template', [ShiftController::class, 'importTemplate'])->name('shifts.import-template');
        Route::post('shifts/import/preview', [ShiftController::class, 'importPreview'])->name('shifts.import.preview');
        Route::post('shifts/import', [ShiftController::class, 'import'])->name('shifts.import');
        Route::resource('shifts', ShiftController::class)->except(['show']);
    });

    // ── Master Data: Shopping Locations ─────────────────────
    Route::middleware(PermissionMiddleware::using('view shopping locations'))->group(function () {
        Route::resource('shopping-locations', ShoppingLocationController::class)->except(['show']);
    });

    // ── Master Data: Delivery Slots ─────────────────────────
    Route::middleware(PermissionMiddleware::using('view delivery slots'))->group(function () {
        Route::resource('delivery-slots', DeliverySlotController::class)->only(['index', 'edit', 'update']);
    });

    // ── Transactions: Cycles ────────────────────────────────
    Route::middleware(PermissionMiddleware::using('view cycles'))->group(function () {
        Route::get('cycles/export', [CycleController::class, 'export'])->name('cycles.export');
        Route::get('cycles/import-template', [CycleController::class, 'importTemplate'])->name('cycles.import-template');
        Route::post('cycles/import/preview', [CycleController::class, 'importPreview'])->name('cycles.import.preview');
        Route::post('cycles/import', [CycleController::class, 'import'])->name('cycles.import');
        Route::get('cycles/quick-receive', [CycleController::class, 'quickReceiveForm'])->name('cycles.quick-receive.form');
        Route::post('cycles/quick-receive', [CycleController::class, 'quickReceiveStore'])->name('cycles.quick-receive.store');
        Route::post('cycles/{cycle}/receive', [CycleController::class, 'receive'])->name('cycles.receive');
        Route::resource('cycles', CycleController::class);
    });

    // ── Transactions: Shopping ──────────────────────────────
    Route::middleware(PermissionMiddleware::using('view shoppings'))->group(function () {
        Route::post('shoppings/{shopping}/ship', [ShoppingController::class, 'ship'])->name('shoppings.ship');
        Route::resource('shoppings', ShoppingController::class);
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
    });

    // ── System (superadmin only) ────────────────────────────
    Route::middleware(RoleMiddleware::using('superadmin'))->group(function () {
        Route::resource('menus', MenuController::class);
        Route::resource('users', UserController::class);
        Route::post('users/import/preview', [UserController::class, 'importPreview'])->name('users.import.preview');
        Route::post('users/import', [UserController::class, 'import'])->name('users.import');
        Route::get('users/export', [UserController::class, 'export'])->name('users.export');
        Route::get('users/import-template', [UserController::class, 'importTemplate'])->name('users.import-template');
        Route::resource('roles', RoleController::class);
        Route::resource('permissions', PermissionController::class);

        Route::get('/test-broadcast', function () {
            $user = auth()->user();
            if (!$user) return 'Anda harus login dulu!';
            $user->notify(new TestRealtimeNotification);
            return 'Notifikasi realtime telah dikirim!';
        });
    });
});

require __DIR__.'/auth.php';
