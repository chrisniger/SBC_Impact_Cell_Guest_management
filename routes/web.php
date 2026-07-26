<?php

use App\Http\Controllers\Auth\RoleSwitchController;
use App\Http\Controllers\ImpactCellController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Phase 02 — multi-role active-role switching (Implementation/00_Laravel_Bridge.md § 5 + § 6)
    // Inentionally does NOT include `verified` middleware: the seeded admin's
    // `email_verified_at` is null, and this preserves emergency admin access.
    // TODO: re-add `verified` here once email verification ships and
    // AdminUserSeeder sets `email_verified_at`.
    Route::post('/auth/switch-role', [RoleSwitchController::class, 'store'])
        ->name('role.switch');

    // Phase 03 — Impact Cell CRUD (Implementation/04_Impact_Cell_Hierarchy.md).
    // Administrator-only via ImpactCellPolicy (created in Phase 04).
    Route::get   ('/impact-cells',                  [ImpactCellController::class, 'index'])->name('impact-cells.index');
    Route::get   ('/impact-cells/{id}',             [ImpactCellController::class, 'show'])->name('impact-cells.show');
    Route::post  ('/impact-cells',                  [ImpactCellController::class, 'store'])->name('impact-cells.store');
    Route::put   ('/impact-cells/{id}',             [ImpactCellController::class, 'update'])->name('impact-cells.update');
    Route::delete('/impact-cells/{id}',             [ImpactCellController::class, 'destroy'])->name('impact-cells.destroy');

    // Phase 04 — Guest CRUD + column-level access (Implementation/Phase_04_Guest_Records_Core.md).
    // Admin / FollowUpOfficer create via GuestPolicy; column-level access
    // enforced by GuestRequest::prepareForValidation() (single source of truth).
    // Inertia pages render at resources/js/Pages/Guests/{Index,Show}.tsx;
    // Phase 05/06/07 will flesh out the per-group dashboards.
    Route::get   ('/guests',                  [\App\Http\Controllers\GuestController::class, 'index'])->name('guests.index');
    Route::post  ('/guests',                  [\App\Http\Controllers\GuestController::class, 'store'])->name('guests.store');
    Route::get   ('/guests/{id}',             [\App\Http\Controllers\GuestController::class, 'show'])->name('guests.show');
    Route::put   ('/guests/{id}',             [\App\Http\Controllers\GuestController::class, 'update'])->name('guests.update');
    Route::delete('/guests/{id}',             [\App\Http\Controllers\GuestController::class, 'destroy'])->name('guests.destroy');
});

require __DIR__.'/auth.php';
