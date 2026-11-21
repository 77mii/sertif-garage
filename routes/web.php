<?php

use App\Http\Controllers\Admin\ExtensionController;
use App\Http\Controllers\Admin\GarageController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Tenant\AccountStatusController;
use App\Http\Controllers\Tenant\RentalController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function (): RedirectResponse {
    $user = Auth::user();

    if ($user === null) {
        return redirect()->route('login');
    }

    if ($user->isAdmin()) {
        return redirect()->route('admin.garages.index');
    }

    if ($user->isApproved()) {
        return redirect()->route('tenant.rentals.create');
    }

    return redirect()->route('tenant.status');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.store');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'tenant.status'])->group(function (): void {
    Route::get('/status-akun', [AccountStatusController::class, 'show'])->name('tenant.status');
});

Route::middleware(['auth', 'tenant.approved'])->group(function (): void {
    Route::get('/sewa/baru', [RentalController::class, 'create'])->name('tenant.rentals.create');
    Route::post('/sewa/baru', [RentalController::class, 'store'])->name('tenant.rentals.store');
});

Route::prefix('admin')
    ->as('admin.')
    ->middleware(['auth', 'admin'])
    ->group(function (): void {
        Route::get('/garasi', [GarageController::class, 'index'])->name('garages.index');
        Route::post('/garasi', [GarageController::class, 'store'])->name('garages.store');

        Route::get('/verifikasi', [VerificationController::class, 'index'])->name('verifications.index');
        Route::post('/verifikasi/{user}/terima', [VerificationController::class, 'approve'])->name('verifications.approve');
        Route::post('/verifikasi/{user}/tolak', [VerificationController::class, 'reject'])->name('verifications.reject');

        Route::get('/perpanjang', [ExtensionController::class, 'index'])->name('extensions.index');
        Route::get('/perpanjang/{rental}', [ExtensionController::class, 'show'])->name('extensions.show');
        Route::post('/perpanjang/{rental}', [ExtensionController::class, 'store'])->name('extensions.store');

        Route::get('/laporan', [ReportController::class, 'index'])->name('reports.index');
    });
