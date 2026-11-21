<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\GarageController;
use App\Http\Controllers\Admin\RentalExtensionController;
use App\Http\Controllers\Admin\RenterVerificationController;
use App\Http\Controllers\Renter\DashboardController as RenterDashboardController;
use App\Http\Controllers\Renter\RentalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// Titik masuk setelah login: arahkan ke dashboard sesuai role (REQ-AUTH-02.1).
Route::get('/dashboard', function (Request $request) {
    return redirect()->route($request->user()->isAdmin() ? 'admin.dashboard' : 'renter.dashboard');
})->middleware('auth')->name('dashboard');

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/garages', [GarageController::class, 'index'])->name('garages.index');
    Route::get('/garages/create', [GarageController::class, 'create'])->name('garages.create');
    Route::post('/garages', [GarageController::class, 'store'])->name('garages.store');

    Route::get('/extensions', [RentalExtensionController::class, 'index'])->name('extensions.index');
    Route::get('/extensions/{rental}/create', [RentalExtensionController::class, 'create'])->name('extensions.create');
    Route::post('/extensions/{rental}/preview', [RentalExtensionController::class, 'preview'])->name('extensions.preview');
    Route::post('/extensions/{rental}', [RentalExtensionController::class, 'store'])->name('extensions.store');

    Route::get('/renters', [RenterVerificationController::class, 'index'])->name('renters.index');
    Route::patch('/renters/{user}/approve', [RenterVerificationController::class, 'approve'])->name('renters.approve');
    Route::patch('/renters/{user}/reject', [RenterVerificationController::class, 'reject'])->name('renters.reject');
});

Route::middleware(['auth', 'role:penyewa'])->prefix('renter')->name('renter.')->group(function () {
    Route::get('/dashboard', [RenterDashboardController::class, 'index'])->name('dashboard');
    Route::get('/rentals', [RentalController::class, 'index'])->name('rentals.index');

    Route::middleware('renter.approved')->group(function () {
        Route::get('/rentals/create', [RentalController::class, 'create'])->name('rentals.create');
        Route::get('/garages/available', [RentalController::class, 'availableGarages'])->name('garages.available');
        Route::post('/rentals/preview', [RentalController::class, 'preview'])->name('rentals.preview');
        Route::post('/rentals', [RentalController::class, 'store'])->name('rentals.store');
    });
});

require __DIR__.'/auth.php';
