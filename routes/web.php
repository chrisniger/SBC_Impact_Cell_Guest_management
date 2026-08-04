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
    // Phase 17 — `create` MUST be registered BEFORE `/{id}` so Laravel's
    // first-match-wins routing doesn't dispatch GET /impact-cells/create
    // into the show handler with id="create" (which would 404 on the UUID
    // findOrFail). Same rule applies to the /create React route used by
    // the admin "Add New Cell" button.
    Route::get   ('/impact-cells/create',           [ImpactCellController::class, 'create'])->name('impact-cells.create');
    Route::get   ('/impact-cells/{id}',             [ImpactCellController::class, 'show'])->name('impact-cells.show');
    Route::post  ('/impact-cells',                  [ImpactCellController::class, 'store'])->name('impact-cells.store');
    Route::put   ('/impact-cells/{id}',             [ImpactCellController::class, 'update'])->name('impact-cells.update');
    // Phase 32 — leadership-team-only PUT. Split from the fat `update()`
    // so an assigned Impact_Leaders can edit their own team (validated +
    // authorized via ImpactCellPolicy::updateLeadership) without ever
    // touching the cell name/hierarchy, which stay behind `update`.
    Route::put   ('/impact-cells/{id}/leadership',  [ImpactCellController::class, 'updateLeadership'])->name('impact-cells.update-leadership');
    Route::delete('/impact-cells/{id}',             [ImpactCellController::class, 'destroy'])->name('impact-cells.destroy');
    // Phase 17 — admin-only re-parenting endpoints used by the Sub-cells
    // editor card on Show.tsx. Both authorize ImpactCellPolicy::update
    // on BOTH the parent and the child (both rows mutate, both gates must
    // pass). Fast-action: no confirm modal.
    Route::post  ('/impact-cells/{id}/attach-sub-cell', [ImpactCellController::class, 'attachSubCell'])->name('impact-cells.attach-sub-cell');
    Route::post  ('/impact-cells/{id}/detach-sub-cell', [ImpactCellController::class, 'detachSubCell'])->name('impact-cells.detach-sub-cell');

    // Phase 04 — Guest CRUD + column-level access (Implementation/Phase_04_Guest_Records_Core.md).
    // Admin / FollowUpOfficer create via GuestPolicy; column-level access
    // enforced by GuestRequest::prepareForValidation() (single source of truth).
    // Inertia pages render at resources/js/Pages/Guests/{Index,Show}.tsx;
    // Phase 05/06/07 will flesh out the per-group dashboards.
    Route::get   ('/guests',                  [\App\Http\Controllers\GuestController::class, 'index'])->name('guests.index');
    Route::post  ('/guests',                  [\App\Http\Controllers\GuestController::class, 'store'])->name('guests.store');
    // Phase 39 — JSON roster for the Assigned Guests inline expandable rows.
    // MUST be registered BEFORE /guests/{id} so 'roster' isn't captured as an id.
    Route::get   ('/guests/roster',           [\App\Http\Controllers\GuestController::class, 'roster'])->name('guests.roster');
    Route::get   ('/guests/{id}/edit',        [\App\Http\Controllers\GuestController::class, 'edit'])->name('guests.edit');
    Route::get   ('/guests/{id}',             [\App\Http\Controllers\GuestController::class, 'show'])->name('guests.show');
    Route::put   ('/guests/{id}',             [\App\Http\Controllers\GuestController::class, 'update'])->name('guests.update');
    Route::delete('/guests/{id}',             [\App\Http\Controllers\GuestController::class, 'destroy'])->name('guests.destroy');

    // Phase 06 — inline follow_up_status quick-update for the team dashboard queue.
    // Returns JSON instead of a redirect so the frontend can apply the change
    // without a full Inertia page reload.
    Route::patch('/guests/{id}/follow-up-status', [\App\Http\Controllers\GuestController::class, 'updateFollowUpStatus'])->name('guests.follow-up-status');

    // Phase 07 — inline impact_status quick-update for the leader dashboard's assigned-guests table.
    // Mirrors Phase 06 follow-up-status: lightweight JSON endpoint, no Inertia redirect.
    Route::patch('/guests/{id}/impact-status', [\App\Http\Controllers\GuestController::class, 'updateImpactStatus'])->name('guests.impact-status');

    // Phase 08 — Leadership Board (JSON endpoint for primary cell health).
    Route::get('/leadership-board/{cellId}', [\App\Http\Controllers\LeadershipBoardController::class, 'show'])->name('leadership-board.show');

    // Phase 08 — Stacked multi-board Inertia page for admin / Impact_Cell_Admin / Zonal / Impact_Leaders.
    // Pre-computes per-primary board data on the server so the page makes ZERO fetch round-trips
    // (would otherwise be 65+ concurrent requests via LeadershipBoard.tsx for an admin — N+1 trap).
    // Impact_Leaders data-leak fix: index() filters primaries to ones the leader has submissions under.
    Route::get('/leadership', [\App\Http\Controllers\LeadershipBoardController::class, 'index'])->name('leadership.index');

    // Phase 06d.0 stub pages — submissions still renders its "Open submissions" link
    // (stays live everywhere). Roles-permissions + messages + analytics remain
    // honest placeholders and are gated behind local/staging by the
    // `gate.stubs` middleware (see GateStubPagesByEnvironment).
    Route::get('/admin/submissions',              fn () => Inertia::render('Admin/Submissions/Index'))->name('admin.submissions.index');
    // Phase 34 — real Roles & Permissions admin UI (replaces the Phase 06d.0
    // "Coming soon" stub). Listing stays behind gate.stubs (production-hidden
    // per design decision); write endpoints stay available for provisioning.
    Route::get   ('/admin/roles-permissions',              [\App\Http\Controllers\Admin\RolesPermissionsController::class, 'index'])->name('admin.roles-permissions.index');
    Route::post  ('/admin/roles-permissions',              [\App\Http\Controllers\Admin\RolesPermissionsController::class, 'store'])->name('admin.roles-permissions.store');
    Route::put   ('/admin/roles-permissions/{role}',       [\App\Http\Controllers\Admin\RolesPermissionsController::class, 'update'])->name('admin.roles-permissions.update');
    Route::delete('/admin/roles-permissions/{role}',       [\App\Http\Controllers\Admin\RolesPermissionsController::class, 'destroy'])->name('admin.roles-permissions.destroy');
    Route::post  ('/admin/roles-permissions/permissions',  [\App\Http\Controllers\Admin\RolesPermissionsController::class, 'storePermission'])->name('admin.roles-permissions.permissions.store');
    // Phase 34 — real in-app announcement board (replaces the Phase 06d.0
    // "Coming soon" stub). Listing stays behind gate.stubs (production-hidden
    // per design decision); writes stay available for provisioning.
    Route::get   ('/admin/messages',                  [\App\Http\Controllers\Admin\MessagesController::class, 'index'])->name('admin.messages.index');
    Route::post  ('/admin/messages',                  [\App\Http\Controllers\Admin\MessagesController::class, 'store'])->name('admin.messages.store');
    Route::delete('/admin/messages/{announcement}',   [\App\Http\Controllers\Admin\MessagesController::class, 'destroy'])->name('admin.messages.destroy');
    // Phase 34 — real Analytics page (replaces the Phase 06d.0 "Coming soon"
    // stub). Reuses the shared AnalyticsService chart math + the existing
    // OverviewAnalytics / DateRangeFilter / KPICard / SystemOverviewPanel
    // components. Listing stays behind gate.stubs (production-hidden).
    Route::get('/admin/analytics',                [\App\Http\Controllers\Admin\AnalyticsController::class, 'index'])->name('admin.analytics.index');

    // Phase 06e+1 — Admin/Users real CRUD (was a Phase 06d.0 stub).
    // Administrator-only via UserPolicy (auto-discovered). The inline
    // delete endpoint enforces a self-delete guard; PATCH role enforces
    // the canSwitchTo() check from the User model.
    //
    // Phase 06e+2 — the GET index is gated behind `gate.stubs` so the
    // CRUD page is hidden in production (mirrors the other 3 admin
    // stubs). The POST/PATCH/DELETE write endpoints deliberately stay
    // available so future migrations can pre-create users without
    // exposing the listing UI prematurely — flip UserController::index
    // to a non-gated Inertia render when ready to ship.
    //
    // Phase 06e+3 — added GET edit + PUT update + PATCH restore + the
    // `?filter=trashed` query param surfaced to the Index page. Three
    // new routes (`edit`, `update`, `restore`) ALSO get `gate.stubs`
    // — they live in admin space and are intentionally disabled in
    // production until Phase 06e+4 ships the full migration of the
    // User CRUD into the production admin chrome.
    Route::get   ('/admin/users',                  [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('admin.users.index');
    Route::post  ('/admin/users',                  [\App\Http\Controllers\Admin\UserController::class, 'store'])->name('admin.users.store');
    Route::get   ('/admin/users/{user}/edit',      [\App\Http\Controllers\Admin\UserController::class, 'edit'])->name('admin.users.edit');
    Route::put   ('/admin/users/{user}',           [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('admin.users.update');
    Route::patch ('/admin/users/{user}/role',      [\App\Http\Controllers\Admin\UserController::class, 'updateRole'])->name('admin.users.update-role');
    Route::patch ('/admin/users/{user}/zonal-cells', [\App\Http\Controllers\Admin\UserController::class, 'updateZonalCells'])->name('admin.users.update-zonal-cells');
    Route::patch ('/admin/users/{user}/restore',   [\App\Http\Controllers\Admin\UserController::class, 'restore'])->name('admin.users.restore');
    Route::delete('/admin/users/{user}',           [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('admin.users.destroy');

    // Phase 09 — Notification settings (Admin only).
    Route::get   ('/notification-settings',               [\App\Http\Controllers\NotificationSettingsController::class, 'index'])->name('notification-settings.index');
    Route::post  ('/notification-settings',               [\App\Http\Controllers\NotificationSettingsController::class, 'store'])->name('notification-settings.store');
    Route::delete('/notification-settings/{id}',          [\App\Http\Controllers\NotificationSettingsController::class, 'destroy'])->name('notification-settings.destroy');

    // Phase 09b — admin test-email endpoint (Send test email button on Settings.tsx).
    Route::post('/notification-settings/test-email', [\App\Http\Controllers\NotificationSettingsController::class, 'testEmail'])->name('notification-settings.test-email');

    // Phase 33 — Admin Settings page: SMTP configuration (writes .env)
    // + Backup & Restore (JSON archive download per scope / full restore).
    // Administrator-only; every endpoint re-checks activeRole in the controller.
    Route::get   ('/admin/settings',                [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('admin.settings.index');
    Route::post  ('/admin/settings/smtp',           [\App\Http\Controllers\Admin\SettingsController::class, 'storeSmtp'])->name('admin.settings.smtp.store');
    Route::post  ('/admin/settings/smtp/test',      [\App\Http\Controllers\Admin\SettingsController::class, 'testEmail'])->name('admin.settings.smtp.test');
    Route::get   ('/admin/settings/backup',         [\App\Http\Controllers\Admin\SettingsController::class, 'backup'])->name('admin.settings.backup');
    Route::post  ('/admin/settings/restore',        [\App\Http\Controllers\Admin\SettingsController::class, 'restore'])->name('admin.settings.restore');

    // Phase 10 — CSV Import / Export (Admin for import, Admin+Officers for export).
    Route::get   ('/csv/import',                          [\App\Http\Controllers\CsvImportController::class, 'index'])->name('csv.import');
    Route::post  ('/csv/import',                          [\App\Http\Controllers\CsvImportController::class, 'import'])->name('csv.import.upload');
    Route::get   ('/csv/export',                          [\App\Http\Controllers\CsvExportController::class, 'export'])->name('csv.export');
    // Phase 10c — downloadable sample CSV per import template (Admin only).
    Route::get   ('/csv/sample/{template?}',              [\App\Http\Controllers\CsvImportController::class, 'sample'])->name('csv.sample');

    // Phase 11 — Reports & Audit.
    Route::get   ('/reports',                             [\App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');
    Route::get   ('/audit',                               [\App\Http\Controllers\AuditLogController::class, 'index'])->name('audit.index');

    // Phase 11b — admin JSON endpoint for audit-log filtering (scriptable clients, curl, future mobile).
    Route::get   ('/api/reports/audit',                   [\App\Http\Controllers\AuditLogController::class, 'apiIndex'])->name('api.reports.audit');

    // Phase 07 — Impact Submissions (Members Data, Reports, Childbirth, Souls).
    // Both the listing and the create form use GET /impact-submissions;
    // the optional `type` query param determines which form renders on create.
    Route::get   ('/impact-submissions',              [\App\Http\Controllers\ImpactSubmissionController::class, 'index'])->name('impact-submissions.index');
    Route::get   ('/impact-submissions/create',       [\App\Http\Controllers\ImpactSubmissionController::class, 'create'])->name('impact-submissions.create');
    Route::post  ('/impact-submissions',              [\App\Http\Controllers\ImpactSubmissionController::class, 'store'])->name('impact-submissions.store');
    Route::get   ('/impact-submissions/{id}',         [\App\Http\Controllers\ImpactSubmissionController::class, 'show'])->name('impact-submissions.show');
    Route::get   ('/impact-submissions/search/json',  [\App\Http\Controllers\ImpactSubmissionController::class, 'search'])->name('impact-submissions.search');
    Route::get   ('/my-reports',                      [\App\Http\Controllers\ImpactSubmissionController::class, 'mySubmissions'])->name('impact-submissions.my-reports');
    Route::get   ('/soul-search',                     [\App\Http\Controllers\ImpactSubmissionController::class, 'soulSearch'])->name('impact-submissions.soul-search');
});

require __DIR__.'/auth.php';
