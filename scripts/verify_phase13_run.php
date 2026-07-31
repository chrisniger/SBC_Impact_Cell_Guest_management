<?php
/**
 * Phase 13 verifier -- AdminSidebar routeName collision fix.
 *
 * Resolves the §1 Phase 12b footer latent bug: AdminSidebar.tsx NAV_ITEMS
 * had routeName: 'notification-settings.index' on both the "Notifications"
 * row AND the "Settings" row. When the /notification-settings route was
 * active, BOTH nav items highlighted -- the bug.
 *
 * Fix: Settings row -> routeName: 'profile.edit' (user-settings fallback
 * per the §1 Phase 12b footer fix-plan). Notifications row unchanged because
 * 'notification-settings.index' is the canonical existing route.
 *
 * Phase 13 follow-up (this turn): rewritten to match the
 * SECTIONS: Section[] architecture (Phase 06d+ consolidation + Phase 09+
 * reorg). Assertions [2][3][11][12] now read from AdminSidebar.tsx:
 *
 *   [2]  Tests `const SECTIONS: Section[] = [` + `type Section = {`
 *        instead of the legacy `const NAV_ITEMS: NavSpec[] = ...`.
 *
 *   [3]  Tests the 13 admin labels inside SECTIONS.admin.items
 *        (a regex'd slice of the admin section's items array).
 *
 *   [11] Tests the active-state contract lives INSIDE AdminSidebar
 *        (`route().current()` + `.routeName` reference). The original
 *        pointed at the now-removed AuthenticatedLayout.tsx; that contract
 *        was consolidated into AdminSidebar.tsx in Phase 06d+.
 *
 *   [12] Tests `resolvedCurrentRoute === item.routeName` (the Phase 09+
 *        identifier name assigning `active={!itemInert && resolvedCurrentRoute === item.routeName}`
 *        on each AdminSidebarNavItem).
 *
 * 13 sub-assertions across: self-syntax (1) -> SECTIONS shape (2-3) ->
 * collision guard (4) -> fix verification (5-6) -> route registration (7-10)
 * -> active-state contract (11-12) -> HANDOFF state (13).
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
$routesPath       = $root . '/routes/web.php';
$handoffPath      = $root . '/HANDOFF.md';

$adminSidebarSrc = is_file($adminSidebarPath) ? file_get_contents($adminSidebarPath) : '';
$routesSrc       = is_file($routesPath)       ? file_get_contents($routesPath)       : '';
$handoffSrc      = is_file($handoffPath)      ? file_get_contents($handoffPath)      : '';

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
// [2] AdminSidebar.tsx defines SECTIONS + Section type (Phase 06d+/09+
//     architecture -- was NAV_ITEMS + NavSpec type in earlier builds).
// ---------------------------------------------------------------------------
check(2, 'AdminSidebar.tsx defines SECTIONS: Section[] array + Section type (Phase 06d+/09+ reorg -- was NAV_ITEMS: NavSpec[] in earlier builds)',
    str_contains($adminSidebarSrc, 'const SECTIONS: Section[] = [')
    && str_contains($adminSidebarSrc, 'type Section = {'),
    'must declare `type Section = { key: string; label: string; items: NavItem[]; }` + `const SECTIONS: Section[] = [...]` -- the role-grouped nav replaces the legacy flat NAV_ITEMS');

// ---------------------------------------------------------------------------
// [3] AdminSidebar.tsx SECTIONS.admin.items contains all 13 expected admin
//     labels (Phase 09 unified sidebar: Dashboard, Guests, Impact Cells,
//     Submissions, Reports, Analytics, CSV Import, Notifications, Messages,
//     Users, Roles & Permissions, Audit Log, Settings).
// ---------------------------------------------------------------------------
$expectedAdminLabels = [
    'Dashboard', 'Guests', 'Impact Cells', 'Submissions', 'Reports',
    'Analytics', 'CSV Import', 'Notifications', 'Messages', 'Users',
    'Roles & Permissions', 'Audit Log', 'Settings',
];
// Doctrine consistency (Phase 06d.0 [3] canonical): end-anchor `[^\s]*\]\s*,?\s*}` AND
// paired-fields doctrine (routeName + iconPath + href all present per item).
preg_match("/key: 'admin',[\s\S]*?items:\s*\[([\s\S]*?)\]\s*,?\s*\}/s", $adminSidebarSrc, $adminSectionMatch);
$adminSectionBlock = $adminSectionMatch[1] ?? '';
$missingLabels = [];
foreach ($expectedAdminLabels as $lbl) {
    if (! preg_match("/label:\s*'" . preg_quote($lbl, '/') . "'/", $adminSectionBlock)) {
        $missingLabels[] = $lbl;
    }
}
$adminHrefCount      = preg_match_all("/href: /",       $adminSectionBlock);
$adminRouteNameCount = preg_match_all("/routeName: '/", $adminSectionBlock);
$adminIconPathCount  = preg_match_all("/iconPath: /",   $adminSectionBlock);
check(3, 'AdminSidebar.tsx SECTIONS.admin.items contains all 13 expected admin labels AND each item has paired (label + href + routeName + iconPath) fields (paired-fields doctrine per Phase 06d.0 [3])  (got labels missing=' . count($missingLabels) . ', href/routeName/iconPath=' . $adminHrefCount . '/' . $adminRouteNameCount . '/' . $adminIconPathCount . ')',
    empty($missingLabels)
        && $adminHrefCount === 13
        && $adminRouteNameCount === 13
        && $adminIconPathCount === 13,
    'SECTIONS.admin.items must include all 13 labels: ' . implode(', ', $expectedAdminLabels) . ' -- missing labels: ' . implode(', ', $missingLabels) .
    ' AND each item must own ALL paired fields (label + href + routeName + iconPath — each count should be 13). If any count is off, the paired-fields doctrine is broken: a maintainer dropped a field assignment or added a shorthand-spread item.');

// ---------------------------------------------------------------------------
// [4] CRITICAL: routeName: 'notification-settings.index' appears EXACTLY ONCE.
// ---------------------------------------------------------------------------
check(4, "CRITICAL: 'notification-settings.index' routeName appears EXACTLY ONCE (collision guard -- the Phase 12b latent bug fix)",
    substr_count($adminSidebarSrc, "routeName: 'notification-settings.index'") === 1,
    "substr_count('routeName: \\\\'notification-settings.index\\\\'') must equal 1 across AdminSidebar.tsx (was 2 before the fix: Notifications + Settings both used it; Phase 13 fix removes Settings' usage)");

// ---------------------------------------------------------------------------
// [5] Notifications row uses 'notification-settings.index' (UNCHANGED -- canonical route).
// ---------------------------------------------------------------------------
// Line-window isolation: each SECTIONS entries row is a single long line. Find
// the line containing `label: 'Notifications'` and assert routeName within.
$notifLine = '';
foreach (explode("\n", $adminSidebarSrc) as $line) {
    if (str_contains($line, "label: 'Notifications'")) {
        $notifLine = $line;
        break;
    }
}
check(5, "Notifications row keeps routeName 'notification-settings.index' (canonical, unchanged)",
    $notifLine !== '' && str_contains($notifLine, "routeName: 'notification-settings.index'"),
    "Notifications row's single-line entry must contain `routeName: 'notification-settings.index'` (the canonical existing route for /notification-settings)");

// ---------------------------------------------------------------------------
// [6] THE FIX: Settings row uses href + routeName BOTH pointing to profile.edit.
// ---------------------------------------------------------------------------
$settingsLine = '';
foreach (explode("\n", $adminSidebarSrc) as $line) {
    if (str_contains($line, "label: 'Settings'")) {
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
// [11] Active-state contract lives in AdminSidebar.tsx (Phase 06d+ retirement
//      of AuthenticatedLayout.tsx consolidated the contract into AdminSidebar).
// ---------------------------------------------------------------------------
check(11, 'Active-state contract lives in AdminSidebar.tsx (Phase 06d+ consolidation: AuthenticatedLayout.tsx was removed; the contract moved into AdminSidebar)',
    str_contains($adminSidebarSrc, 'route().current()')
    && str_contains($adminSidebarSrc, 'routeName'),
    'must resolve the current route name from `(window as any).route().current() ?? ...` and reference item.routeName for active highlighting inside AdminSidebar.tsx -- Phase 06d+ consolidated the active-state from the removed AuthenticatedLayout.tsx into AdminSidebar');

// ---------------------------------------------------------------------------
// [12] AdminSidebar.tsx computes resolvedCurrentRoute + uses it in `=== item.routeName`
//      for the active highlight (Phase 09+ identifier name on AdminSidebarNavItem).
// ---------------------------------------------------------------------------
check(12, 'AdminSidebar.tsx uses `resolvedCurrentRoute === item.routeName` for active highlighting on AdminSidebarNavItem',
    preg_match('/resolvedCurrentRoute\s*===\s*item\.routeName/', $adminSidebarSrc) === 1
    && str_contains($adminSidebarSrc, 'active='),
    "must compute `const resolvedCurrentRoute = (window as any).route().current() ?? '\\';` and pass `active={!itemInert && resolvedCurrentRoute === item.routeName}` to each AdminSidebarNavItem -- the Phase 09+ identifier name (was `currentRoute` in the AuthenticatedLayout delegation; renamed to `resolvedCurrentRoute` when the contract moved to AdminSidebar)");

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
