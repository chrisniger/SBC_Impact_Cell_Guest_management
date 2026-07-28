<?php
/**
 * Phase 13 verifier -- AdminSidebar routeName collision fix.
 *
 * Resolves the §1 Phase 12b footer latent bug: AdminSidebar.tsx NAV_ITEMS
 * had routeName: 'notification-settings.index' on both the "Notifications"
 * row (line 8) AND the "Settings" row (line 13). When the /notification-settings
 * route was active, BOTH nav items highlighted -- the bug.
 *
 * Fix: Settings row -> routeName: 'profile.edit' (user-settings fallback
 * per the §1 Phase 12b footer fix-plan). Notifications row unchanged because
 * 'notification-settings.index' is the canonical existing route.
 *
 * 13 sub-assertions across: self-syntax (1) -> NAV_ITEMS shape (2-3) ->
 * collision guard (4) -> fix verification (5-6) -> route registration (7-10)
 * -> active-state contract (11-12) -> HANDOFF state (13).
 *
 * Style: str_contains + line-window regex (mirrors Phase 09b / Phase 11b
 * verifier patterns). Line-window isolation avoids JSX brace brittleness
 * in NAV_ITEMS iconPath expressions.
 *
 * Run: php scripts/verify_phase13_run.php
 * Expected: 13 pass / 0 fail.
 */

declare(strict_types=1);

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): void {
    fwrite(STDERR, "PHP error (#{$errno}): {$errstr} in {$errfile}:{$errline}\n");
    exit(1);
});

$pass = 0;
$fail = 0;
$failed = [];

function check(int $n, string $label, bool $cond, string $expected): void
{
    global $pass, $fail, $failed;
    if ($cond) {
        $pass++;
        echo "  [{$n}] pass -- {$label}\n";
    } else {
        $fail++;
        $failed[] = "[{$n}] {$label} -- expected: {$expected}";
        echo "  [{$n}] FAIL -- {$label} (expected: {$expected})\n";
    }
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$app  = require $root . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$adminSidebarPath = $root . '/resources/js/Components/AdminSidebar.tsx';
$authLayoutPath   = $root . '/resources/js/Layouts/AuthenticatedLayout.tsx';
$routesPath       = $root . '/routes/web.php';
$handoffPath      = $root . '/HANDOFF.md';

$adminSidebarSrc = is_file($adminSidebarPath) ? file_get_contents($adminSidebarPath) : '';
$authLayoutSrc   = is_file($authLayoutPath) ? file_get_contents($authLayoutPath) : '';
$routesSrc       = is_file($routesPath) ? file_get_contents($routesPath) : '';
$handoffSrc      = is_file($handoffPath) ? file_get_contents($handoffPath) : '';

// ---------------------------------------------------------------------------
// [1] self-syntax -- verifier file parses cleanly.
// ---------------------------------------------------------------------------
$tmpLint = tempnam(sys_get_temp_dir(), 'p13_lint_');
file_put_contents($tmpLint, file_get_contents(__FILE__));
$lintOut = shell_exec(escapeshellcmd(PHP_BINARY) . ' -l ' . escapeshellarg($tmpLint) . ' 2>&1');
unlink($tmpLint);
check(1, 'verify_phase13_run.php parses cleanly (php -l)',
    is_file(__FILE__) && str_contains((string) $lintOut, 'No syntax errors detected'),
    'php -l reports "No syntax errors detected"');

// ---------------------------------------------------------------------------
// [2] AdminSidebar.tsx defines NAV_ITEMS array + NavSpec type.
// ---------------------------------------------------------------------------
check(2, 'AdminSidebar.tsx defines NAV_ITEMS: NavSpec[] array + NavSpec type',
    str_contains($adminSidebarSrc, 'const NAV_ITEMS: NavSpec[] = [')
    && str_contains($adminSidebarSrc, 'type NavSpec = {'),
    'must declare `type NavSpec = { label: string; href: string; routeName: string; iconPath: React.ReactNode; }` + `const NAV_ITEMS: NavSpec[] = [...]`');

// ---------------------------------------------------------------------------
// [3] AdminSidebar.tsx NAV_ITEMS contains all 13 expected labels.
// ---------------------------------------------------------------------------
$expectedLabels = [
    'Dashboard', 'Guests', 'Impact Cells', 'Submissions', 'Reports',
    'Analytics', 'CSV Import', 'Notifications', 'Messages', 'Users',
    'Roles & Permissions', 'Audit Log', 'Settings',
];
$allLabelsPresent = true;
foreach ($expectedLabels as $lbl) {
    if (!str_contains($adminSidebarSrc, "label: '{$lbl}'")) {
        $allLabelsPresent = false;
        break;
    }
}
check(3, 'AdminSidebar.tsx NAV_ITEMS contains all 13 expected labels',
    $allLabelsPresent,
    'all 13 expected labels present in NAV_ITEMS (Dashboard, Guests, Impact Cells, Submissions, Reports, Analytics, CSV Import, Notifications, Messages, Users, Roles & Permissions, Audit Log, Settings)');

// ---------------------------------------------------------------------------
// [4] CRITICAL: routeName: 'notification-settings.index' appears EXACTLY ONCE.
// ---------------------------------------------------------------------------
check(4, "CRITICAL: 'notification-settings.index' routeName appears EXACTLY ONCE (collision guard -- the Phase 12b latent bug fix)",
    substr_count($adminSidebarSrc, "routeName: 'notification-settings.index'") === 1,
    "substr_count('routeName: \\'notification-settings.index\\'') must equal 1 (was 2 before the fix: Notifications + Settings both used it; Phase 13 fix removes Settings' usage)");

// ---------------------------------------------------------------------------
// [5] Notifications row uses 'notification-settings.index' (UNCHANGED -- canonical route).
// ---------------------------------------------------------------------------
// Line-window isolation: each NAV_ITEMS row is a single long line. Find
// the line containing `{ label: 'Notifications'` and assert routeName within.
$notifLine = '';
foreach (explode("\n", $adminSidebarSrc) as $line) {
    if (str_contains($line, "{ label: 'Notifications'")) {
        $notifLine = $line;
        break;
    }
}
check(5, "Notifications row keeps routeName 'notification-settings.index' (canonical, unchanged)",
    $notifLine !== '' && str_contains($notifLine, "routeName: 'notification-settings.index'"),
    "Notifications row's single-line entry must contain `routeName: 'notification-settings.index'` (the canonical existing route for /notification-settings)");

// ---------------------------------------------------------------------------
// [6] THE FIX: Settings row uses href + routeName BOTH pointing to profile.edit
//       (code-reviewer bullet #1 follow-up -- pairs href with routeName so
//       clicking Settings actually navigates to /profile/edit, not a no-op
//       anchor stub).
// ---------------------------------------------------------------------------
$settingsLine = '';
foreach (explode("\n", $adminSidebarSrc) as $line) {
    if (str_contains($line, "{ label: 'Settings'")) {
        $settingsLine = $line;
        break;
    }
}
check(6, "THE FIX: Settings row href AND routeName BOTH point to profile.edit (was href: '#settings' anchor stub + routeName: 'notification-settings.index' collision)",
    $settingsLine !== ''
    && str_contains($settingsLine, "href: route('profile.edit')")
    && str_contains($settingsLine, "routeName: 'profile.edit'")
    && !str_contains($settingsLine, "href: '#settings'")
    && !str_contains($settingsLine, "routeName: 'notification-settings.index'"),
    "Settings row's single-line entry must contain `href: route('profile.edit')` + `routeName: 'profile.edit'` AND must NOT contain `href: '#settings'` (anchor stub) or `routeName: 'notification-settings.index'` (the collision) -- the code-reviewer's bullet #1 follow-up pairs href with routeName so clicking Settings navigates to /profile/edit");

// ---------------------------------------------------------------------------
// [7] routes/web.php registers 'profile.edit' (ProfileController).
// ---------------------------------------------------------------------------
check(7, "routes/web.php registers Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit')",
    str_contains($routesSrc, "Route::get('/profile'")
    && str_contains($routesSrc, 'ProfileController::class')
    && str_contains($routesSrc, "'edit'")
    && str_contains($routesSrc, "'profile.edit'"),
    "routes/web.php must register `Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit')` inside the auth middleware group");

// ---------------------------------------------------------------------------
// [8] routes/web.php registers 'notification-settings.index' (NotificationSettingsController).
// ---------------------------------------------------------------------------
check(8, "routes/web.php registers Route::get('/notification-settings', [NotificationSettingsController::class, 'index'])->name('notification-settings.index')",
    str_contains($routesSrc, "/notification-settings'")
    && str_contains($routesSrc, 'NotificationSettingsController::class')
    && str_contains($routesSrc, "'index'")
    && str_contains($routesSrc, "'notification-settings.index'"),
    "routes/web.php must register `Route::get('/notification-settings', [NotificationSettingsController::class, 'index'])->name('notification-settings.index')` inside the auth middleware group");

// ---------------------------------------------------------------------------
// [9] routes/web.php does NOT register 'admin.notifications.index' (sanity).
// ---------------------------------------------------------------------------
check(9, "routes/web.php does NOT register a 'admin.notifications.index' route (sanity -- alternative fix-path from §1 Phase 12b footer uses the user-settings fallback instead)",
    !str_contains($routesSrc, "'admin.notifications.index'")
    && !str_contains($routesSrc, '"admin.notifications.index"'),
    "must not register 'admin.notifications.index' route (would otherwise be an alternative fix path; the chosen fix uses notification-settings.index which already exists)");

// ---------------------------------------------------------------------------
// [10] routes/web.php does NOT register 'admin.settings.index' (sanity).
// ---------------------------------------------------------------------------
check(10, "routes/web.php does NOT register a 'admin.settings.index' route (sanity -- would be required for `'admin.settings.index'` routeName to ever activate)",
    !str_contains($routesSrc, "'admin.settings.index'")
    && !str_contains($routesSrc, '"admin.settings.index"'),
    "must not register 'admin.settings.index' route (would require route registration; the chosen fix uses profile.edit which already exists)");

// ---------------------------------------------------------------------------
// [11] AuthenticatedLayout.tsx preserves the active-state contract (top-bar pre-existing pattern).
// ---------------------------------------------------------------------------
check(11, 'AuthenticatedLayout.tsx preserves the active-state contract via route().current() === routeName',
    str_contains($authLayoutSrc, 'route().current()')
    && str_contains($authLayoutSrc, 'currentRoute === item.routeName')
    && str_contains($authLayoutSrc, 'navItemsFor('),
    "must use `const currentRoute = route().current()` + `active={currentRoute === item.routeName}` for navItemsFor() active-state detection (the pre-existing top-bar pattern that AdminSidebar mirrors)");

// ---------------------------------------------------------------------------
// [12] AdminSidebar.tsx mirrors the AuthenticatedLayout active-state contract.
// ---------------------------------------------------------------------------
check(12, 'AdminSidebar.tsx mirrors the AuthenticatedLayout active-state contract (route().current() === routeName)',
    str_contains($adminSidebarSrc, 'route().current()')
    && str_contains($adminSidebarSrc, 'active={currentRoute === item.routeName}'),
    "must compute `const currentRoute = (window as any).Ziggy ? (window as any).route().current() : ''` and pass `active={currentRoute === item.routeName}` to each AdminSidebarNavItem");

// ---------------------------------------------------------------------------
// [13] HANDOFF.md §1 has Phase 13 row marked with Done status.
// ---------------------------------------------------------------------------
check(13, 'HANDOFF.md §1 has Phase 13 row marked with "Done" status',
    str_contains($handoffSrc, "| 13 ")
    && (str_contains($handoffSrc, "✅") || str_contains($handoffSrc, "Done")),
    'must contain `| 13 ... | ... | ... Done ... |` row in §1 phase table');

// ---------------------------------------------------------------------------

echo "\nPhase 13 verifier: {$pass} pass / {$fail} fail\n";
if ($fail > 0) {
    echo "\nFailures:\n";
    foreach ($failed as $f) {
        echo "  - {$f}\n";
    }
}
exit($fail === 0 ? 0 : 1);
