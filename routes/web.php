<?php

use App\Http\Controllers\MenuController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\CycleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RackController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\VehicleModelController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\JobPositionController;
use App\Http\Controllers\WorkLocationController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Notifications\TestRealtimeNotification;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/tv-dashboard', [App\Http\Controllers\TvDashboardController::class, 'index'])->name('tv-dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('menus', MenuController::class);
    Route::post('users/import/preview', [UserController::class, 'importPreview'])->name('users.import.preview');
    Route::post('users/import', [UserController::class, 'import'])->name('users.import');
    Route::get('users/export', [UserController::class, 'export'])->name('users.export');
    Route::get('users/import-template', [UserController::class, 'importTemplate'])->name('users.import-template');
    Route::resource('users', UserController::class);
    Route::get('import-status/{importLog}', [App\Http\Controllers\ImportStatusController::class, 'show'])->name('import.status');

    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);
    Route::post('suppliers/import/preview', [SupplierController::class, 'importPreview'])->name('suppliers.import.preview');
    Route::post('suppliers/import', [SupplierController::class, 'import'])->name('suppliers.import');
    Route::get('suppliers/export', [SupplierController::class, 'export'])->name('suppliers.export');
    Route::get('suppliers/import-template', [SupplierController::class, 'importTemplate'])->name('suppliers.import-template');
    Route::resource('suppliers', SupplierController::class);
    Route::post('products/import/preview', [ProductController::class, 'importPreview'])->name('products.import.preview');
    Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
    Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
    Route::get('products/import-template', [ProductController::class, 'importTemplate'])->name('products.import-template');
    Route::resource('products', ProductController::class);

    // Transaction routes
    Route::post('racks/import/preview', [RackController::class, 'importPreview'])->name('racks.import.preview');
    Route::post('racks/import', [RackController::class, 'import'])->name('racks.import');
    Route::get('racks/export', [RackController::class, 'export'])->name('racks.export');
    Route::get('racks/import-template', [RackController::class, 'importTemplate'])->name('racks.import-template');
    Route::resource('racks', RackController::class);
    Route::get('cycles/quick-receive', [CycleController::class, 'quickReceiveForm'])->name('cycles.quick-receive.form');
    Route::post('cycles/quick-receive', [CycleController::class, 'quickReceiveStore'])->name('cycles.quick-receive.store');
    Route::resource('cycles', CycleController::class);
    Route::post('cycles/{cycle}/receive', [CycleController::class, 'receive'])->name('cycles.receive');

    Route::resource('shipments', ShipmentController::class);
    Route::post('shipments/{shipment}/ship', [ShipmentController::class, 'ship'])->name('shipments.ship');
    Route::resource('stocks', StockController::class)->only(['index']);

    Route::get('reports/receiving', [App\Http\Controllers\ReportController::class, 'receiving'])->name('reports.receiving');
    Route::get('reports/receiving/export', [App\Http\Controllers\ReportController::class, 'receivingExport'])->name('reports.receiving.export');
    Route::get('reports/shipment', [App\Http\Controllers\ReportController::class, 'shipment'])->name('reports.shipment');
    Route::get('reports/shipment/export', [App\Http\Controllers\ReportController::class, 'shipmentExport'])->name('reports.shipment.export');

    Route::post('vehicle-models/import/preview', [VehicleModelController::class, 'importPreview'])->name('vehicle-models.import.preview');
    Route::post('vehicle-models/import', [VehicleModelController::class, 'import'])->name('vehicle-models.import');
    Route::get('vehicle-models/export', [VehicleModelController::class, 'export'])->name('vehicle-models.export');
    Route::get('vehicle-models/import-template', [VehicleModelController::class, 'importTemplate'])->name('vehicle-models.import-template');
    Route::resource('vehicle-models', VehicleModelController::class)->except(['show']);
    Route::post('product-categories/import/preview', [ProductCategoryController::class, 'importPreview'])->name('product-categories.import.preview');
    Route::post('product-categories/import', [ProductCategoryController::class, 'import'])->name('product-categories.import');
    Route::get('product-categories/export', [ProductCategoryController::class, 'export'])->name('product-categories.export');
    Route::get('product-categories/import-template', [ProductCategoryController::class, 'importTemplate'])->name('product-categories.import-template');
    Route::resource('product-categories', ProductCategoryController::class)->except(['show']);
    Route::post('job-positions/import/preview', [JobPositionController::class, 'importPreview'])->name('job-positions.import.preview');
    Route::post('job-positions/import', [JobPositionController::class, 'import'])->name('job-positions.import');
    Route::get('job-positions/export', [JobPositionController::class, 'export'])->name('job-positions.export');
    Route::get('job-positions/import-template', [JobPositionController::class, 'importTemplate'])->name('job-positions.import-template');
    Route::resource('job-positions', JobPositionController::class)->except(['show']);
    Route::post('work-locations/import/preview', [WorkLocationController::class, 'importPreview'])->name('work-locations.import.preview');
    Route::post('work-locations/import', [WorkLocationController::class, 'import'])->name('work-locations.import');
    Route::get('work-locations/export', [WorkLocationController::class, 'export'])->name('work-locations.export');
    Route::get('work-locations/import-template', [WorkLocationController::class, 'importTemplate'])->name('work-locations.import-template');
    Route::resource('work-locations', WorkLocationController::class)->except(['show']);
    Route::post('departments/import/preview', [DepartmentController::class, 'importPreview'])->name('departments.import.preview');
    Route::post('departments/import', [DepartmentController::class, 'import'])->name('departments.import');
    Route::get('departments/export', [DepartmentController::class, 'export'])->name('departments.export');
    Route::get('departments/import-template', [DepartmentController::class, 'importTemplate'])->name('departments.import-template');
    Route::resource('departments', DepartmentController::class)->except(['show']);
    Route::post('employees/import/preview', [EmployeeController::class, 'importPreview'])->name('employees.import.preview');
    Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import');
    Route::get('employees/export', [EmployeeController::class, 'export'])->name('employees.export');
    Route::get('employees/import-template', [EmployeeController::class, 'importTemplate'])->name('employees.import-template');
    Route::resource('employees', EmployeeController::class);

    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllRead');
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markAsRead'])->name('notifications.markRead');

    Route::get('/test-broadcast', function () {
        $user = auth()->user();
        if (! $user) {
            return 'Anda harus login dulu untuk mencoba test ini!';
        }
        $user->notify(new TestRealtimeNotification);

        return 'Notifikasi realtime telah dikirim!';
    });
});

require __DIR__.'/auth.php';
