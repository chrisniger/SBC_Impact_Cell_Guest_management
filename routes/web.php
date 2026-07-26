<?php

use App\Http\Controllers\Auth\RoleSwitchController;
use App\Http\Controllers\DashboardController;
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

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

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
    Route::get   ('/guests/{id}/edit',        [\App\Http\Controllers\GuestController::class, 'edit'])->name('guests.edit');
    Route::get   ('/guests/{id}',             [\App\Http\Controllers\GuestController::class, 'show'])->name('guests.show');
    Route::put   ('/guests/{id}',             [\App\Http\Controllers\GuestController::class, 'update'])->name('guests.update');
    Route::delete('/guests/{id}',             [\App\Http\Controllers\GuestController::class, 'destroy'])->name('guests.destroy');

    // Phase 06 — inline follow_up_status quick-update for the team dashboard queue.
    // Returns JSON instead of a redirect so the frontend can apply the change
    // without a full Inertia page reload.
    Route::patch('/guests/{id}/follow-up-status', [\App\Http\Controllers\GuestController::class, 'updateFollowUpStatus'])->name('guests.follow-up-status');

    // Phase 08 — Leadership Board (JSON endpoint for primary cell health).
    Route::get('/leadership-board/{cellId}', [\App\Http\Controllers\LeadershipBoardController::class, 'show'])->name('leadership-board.show');

    // Phase 09 — Notification settings (Admin only).
    Route::get   ('/notification-settings',               [\App\Http\Controllers\NotificationSettingsController::class, 'index'])->name('notification-settings.index');
    Route::post  ('/notification-settings',               [\App\Http\Controllers\NotificationSettingsController::class, 'store'])->name('notification-settings.store');
    Route::delete('/notification-settings/{id}',          [\App\Http\Controllers\NotificationSettingsController::class, 'destroy'])->name('notification-settings.destroy');

    // Phase 10 — CSV Import / Export (Admin for import, Admin+Officers for export).
    Route::get   ('/csv/import',                          [\App\Http\Controllers\CsvImportController::class, 'index'])->name('csv.import');
    Route::post  ('/csv/import',                          [\App\Http\Controllers\CsvImportController::class, 'import'])->name('csv.import.upload');
    Route::get   ('/csv/export',                          [\App\Http\Controllers\CsvExportController::class, 'export'])->name('csv.export');

    // Phase 11 — Reports & Audit.
    Route::get   ('/reports',                             [\App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');
    Route::get   ('/audit',                               [\App\Http\Controllers\AuditLogController::class, 'index'])->name('audit.index');

    // Phase 07 — Impact Submissions (Members Data, Reports, Childbirth, Souls).
    // Both the listing and the create form use GET /impact-submissions;
    // the optional `type` query param determines which form renders on create.
    Route::get   ('/impact-submissions',              [\App\Http\Controllers\ImpactSubmissionController::class, 'index'])->name('impact-submissions.index');
    Route::get   ('/impact-submissions/create',       [\App\Http\Controllers\ImpactSubmissionController::class, 'create'])->name('impact-submissions.create');
    Route::post  ('/impact-submissions',              [\App\Http\Controllers\ImpactSubmissionController::class, 'store'])->name('impact-submissions.store');
    Route::get   ('/impact-submissions/{id}',         [\App\Http\Controllers\ImpactSubmissionController::class, 'show'])->name('impact-submissions.show');
    Route::get   ('/impact-submissions/search/json',  [\App\Http\Controllers\ImpactSubmissionController::class, 'search'])->name('impact-submissions.search');
    Route::get   ('/my-reports',                      [\App\Http\Controllers\ImpactSubmissionController::class, 'myReports'])->name('impact-submissions.my-reports');
    Route::get   ('/soul-search',                     [\App\Http\Controllers\ImpactSubmissionController::class, 'soulSearch'])->name('impact-submissions.soul-search');
});

require __DIR__.'/auth.php';
