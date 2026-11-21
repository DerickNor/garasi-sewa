<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Renter\DashboardController as RenterDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// Titik masuk setelah login: arahkan ke dashboard sesuai role (REQ-AUTH-02.1).
Route::get('/dashboard', function (Request $request) {
    return redirect()->route($request->user()->isAdmin() ? 'admin.dashboard' : 'renter.dashboard');
})->middleware('auth')->name('dashboard');

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'role:penyewa'])->prefix('renter')->name('renter.')->group(function () {
    Route::get('/dashboard', [RenterDashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/auth.php';
