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
use App\Http\Controllers\UserController;
use App\Notifications\TestRealtimeNotification;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('menus', MenuController::class);
    Route::resource('users', UserController::class);
    Route::post('users/import/preview', [UserController::class, 'importPreview'])->name('users.import.preview');
    Route::post('users/import', [UserController::class, 'import'])->name('users.import');
    Route::get('users/export', [UserController::class, 'export'])->name('users.export');
    Route::get('import-status/{importLog}', [UserController::class, 'importStatus'])->name('import.status');

    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);
    Route::resource('suppliers', SupplierController::class);
    Route::resource('products', ProductController::class);

    // Transaction routes
    Route::resource('racks', RackController::class);
    Route::resource('cycles', CycleController::class);
    Route::post('cycles/{cycle}/receive', [CycleController::class, 'receive'])->name('cycles.receive');

    Route::resource('shipments', ShipmentController::class);
    Route::post('shipments/{shipment}/ship', [ShipmentController::class, 'ship'])->name('shipments.ship');

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
