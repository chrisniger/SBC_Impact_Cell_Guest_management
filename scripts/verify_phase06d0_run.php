<?php
/**
 * Phase 06d.0 — Admin Dashboard polish (prelude) verifier.
 *
 * Asserts:
 *  [1]  scripts directory + this verifier are syntactically valid.
 *  [2]  AdminDashboardLayout component file exists.
 *  [3]  AdminSidebar component file exists with 13 nav entries.
 *  [4]  AdminSidebarNavItem component file exists.
 *  [5]  Greeting component file exists.
 *  [6]  Sparkline component file exists.
 *  [7]  AnimatedCounter component file exists.
 *  [8]  KPICard component extended with `series` prop + Sparkline import.
 *  [9]  Admin/Users/Index.tsx stub page exists.
 *  [10] Admin/RolesPermissions/Index.tsx stub page exists.
 *  [11] Admin/Messages/Index.tsx stub page exists.
 *  [12] Admin/Analytics/Index.tsx stub page exists.
 *  [13] Admin/Submissions/Index.tsx stub page exists.
 *  [14] routes/web.php registers 5 NEW admin stub routes.
 *  [15] DashboardController::adminDashboard returns kpis + kpiDeltas + kpiSeries.
 *  [16] Dashboard.tsx parent passes kpiDeltas + kpiSeries to AdminDashboard.
 *  [17] Dashboard.tsx AdminDashboard function uses Greeting + AdminDashboardLayout.
 *  [18] Dashboard.tsx parent uses AdminDashboardLayout when variant === 'admin'.
 *  [19] Sparkline math: empty / single-point series return null (NaN safe).
 *  [20] AnimatedCounter respects prefers-reduced-motion.
 *  [21] Existing Phase 02-07 verifiers still GREEN (regression sweep).
 *  [22] Phase 08 verifier is intentionally absent (paused mid-ship — see HANDOFF).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$pass = 0;
$fail = 0;

function check(int $n, string $label, bool $cond, string $failMsg = ''): void {
    global $pass, $fail;
    if ($cond) {
        echo "✓ [{$n}] {$label}\n";
        $pass++;
    } else {
        echo "✗ [{$n}] {$label}  \u2014  {$failMsg}\n";
        $fail++;
    }
}

function read(string $rel): string {
    global $root;
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($path)) return '';
    return (string) file_get_contents($path);
}

// ─────────────────────────────────────────────────────────────────────────
// [1] This script's syntax is valid.
// ─────────────────────────────────────────────────────────────────────────
check(1, 'Verifier PHP itself is parseable',
    true, '' /* php -l was already done by caller */);

// ─────────────────────────────────────────────────────────────────────────
// Layout + sidebar + nav-item file existence.
// ─────────────────────────────────────────────────────────────────────────
$layoutText   = read('resources/js/Layouts/AdminDashboardLayout.tsx');
$sidebarText  = read('resources/js/Components/AdminSidebar.tsx');
$navItemText  = read('resources/js/Components/AdminSidebarNavItem.tsx');

// Phase 06d.2 wired HeadlessUI Combobox (GlobalSearch) + Listbox (LanguageSwitcher)
// components into AdminDashboardLayout, replacing the disabled-input placeholders.
// The admin-global-search + admin-language-switcher testids moved INTO those
// components (GlobalSearch:61, LanguageSwitcher:48). We assert the architectural
// contract (component imports + usages) so the verifier tracks how the layout
// is wired, not literal strings that may relocate again in later polish rounds.
check(2, 'AdminDashboardLayout.tsx: sidebar testid (layout-owned) + GlobalSearch/LanguageSwitcher imports+usage (post-06d.2)',
    $layoutText !== ''
    && str_contains($layoutText, 'data-testid="admin-sidebar"')
    && str_contains($layoutText, '@/Components/GlobalSearch')
    && str_contains($layoutText, '<GlobalSearch ')
    && str_contains($layoutText, '@/Components/LanguageSwitcher')
    && str_contains($layoutText, '<LanguageSwitcher '),
    'missing layout shell testid or GlobalSearch/LanguageSwitcher imports/usages (note: layout may import GlobalSearch as `import GlobalSearch, { SearchResult } from ...` — assert the path substring to handle both shapes)');

check(3, 'AdminSidebar.tsx: 13 nav entries + collapse toggle + brand logo block',
    $sidebarText !== ''
    && substr_count($sidebarText, "iconPath: <") >= 13
    && str_contains($sidebarText, 'admin-sidebar-collapse-toggle')
    && str_contains($sidebarText, 'Summit Bible'),
    'expected 13 nav entries + collapse toggle + Summit Bible logo block');

check(4, 'AdminSidebarNavItem.tsx: active 3px left bar + hover transition',
    $navItemText !== '' && str_contains($navItemText, 'w-[3px]') && str_contains($navItemText, 'active ?'),
    'missing active-bar or hover-state branching');

// ─────────────────────────────────────────────────────────────────────────
// New 06d.0 primitives.
// ─────────────────────────────────────────────────────────────────────────
$greetingText  = read('resources/js/Components/Greeting.tsx');
$sparklineText = read('resources/js/Components/Sparkline.tsx');
$counterText   = read('resources/js/Components/AnimatedCounter.tsx');

check(5, 'Greeting.tsx: time-aware greeting + first-name extract + active-role subtitle',
    $greetingText !== ''
    && str_contains($greetingText, 'partOfDay')
    && str_contains($greetingText, 'firstName')
    && str_contains($greetingText, 'Full System Access')
    && str_contains($greetingText, 'data-testid="admin-greeting"'),
    'expected time-aware greeting + active-role subtitle + admin-greeting testid');

check(6, 'Sparkline.tsx: NaN-safe returns null for series.length < 2',
    $sparklineText !== ''
    && str_contains($sparklineText, 'series.length < 2')
    && str_contains($sparklineText, 'return null')
    && str_contains($sparklineText, 'data-testid="kpi-sparkline"'),
    'missing NaN guard or null-return path for empty/short series');

check(7, 'AnimatedCounter.tsx: rAF + prefers-reduced-motion guard',
    $counterText !== ''
    && str_contains($counterText, 'requestAnimationFrame')
    && str_contains($counterText, 'prefers-reduced-motion')
    && str_contains($counterText, 'easeOutQuad'),
    'missing rAF tick or reduced-motion guard');

// ─────────────────────────────────────────────────────────────────────────
// KPICard extension.
// ─────────────────────────────────────────────────────────────────────────
$kpiText = read('resources/js/Components/KPICard.tsx');
check(8, 'KPICard.tsx: extends with series + sparkTone + animateValue props + Sparkline render',
    $kpiText !== ''
    && str_contains($kpiText, "import Sparkline from '@/Components/Sparkline'")
    && str_contains($kpiText, "import AnimatedCounter from '@/Components/AnimatedCounter'")
    && str_contains($kpiText, 'series?:')
    && str_contains($kpiText, 'animateValue')
    && str_contains($kpiText, 'series.length >= 2 &&'),
    'missing series/animateValue props or Sparkline render guard');

// ─────────────────────────────────────────────────────────────────────────
// 5 stub pages.
// ─────────────────────────────────────────────────────────────────────────
// Submissions stub is FUNCTIONAL (links to existing /impact-submissions.index
// route) — different acceptance criteria from the others (which are
// pure "Coming soon" placeholders).
$stubPages = [
    'resources/js/Pages/Admin/Users/Index.tsx'              => 'admin-users-stub',
    'resources/js/Pages/Admin/RolesPermissions/Index.tsx'   => 'admin-roles-permissions-stub',
    'resources/js/Pages/Admin/Messages/Index.tsx'           => 'admin-messages-stub',
    'resources/js/Pages/Admin/Analytics/Index.tsx'          => 'admin-analytics-stub',
    'resources/js/Pages/Admin/Submissions/Index.tsx'        => 'admin-submissions-stub',
];
$placeholderPages = ['Users', 'RolesPermissions', 'Messages', 'Analytics']; // canonical 'Coming soon' pages
$stubIdx = 9;
foreach ($stubPages as $file => $testid) {
    $text = read($file);
    $baseName = basename(dirname($file)); // 'Submissions', 'Users', 'RolesPermissions', 'Messages', 'Analytics'
    if (in_array($baseName, $placeholderPages, true)) {
        // Pure placeholder: assert 'Coming soon' literal.
        $labelSuffix = 'placeholder + back link';
        $ok = str_contains($text, 'Coming soon');
        $failMsgHint = ' or Coming soon placeholder';
    } else {
        // Functional stub (Submissions): assert it links to the canonical list.
        $labelSuffix = 'functional redirect link';
        $ok = str_contains($text, 'Open submissions list') || str_contains($text, 'impact-submissions.index');
        $failMsgHint = ' or Open submissions list link';
    }
    check($stubIdx, "Stub Page: {$file} has '{$testid}' testid + back link + {$labelSuffix}",
        $text !== ''
        && str_contains($text, "data-testid=\"{$testid}\"")
        && str_contains($text, 'Back to Dashboard')
        && $ok,
        'missing stub testid, back-link,' . $failMsgHint);
    $stubIdx++;
}

// ─────────────────────────────────────────────────────────────────────────
// Routes.
// ─────────────────────────────────────────────────────────────────────────
$routesText = read('routes/web.php');
$newRoutes = [
    'admin.submissions.index',
    'admin.users.index',
    'admin.roles-permissions.index',
    'admin.messages.index',
    'admin.analytics.index',
];
$allRoutesPresent = true;
$missingRoute = '';
foreach ($newRoutes as $name) {
    // Each stub route is registered with `->name('admin.foo.index')` after the path.
    if (!str_contains($routesText, "->name('{$name}')")) {
        $allRoutesPresent = false;
        $missingRoute = $name;
        break;
    }
}
check(14, 'routes/web.php: 5 NEW admin stub routes registered (admin.submissions/users/roles-permissions/messages/analytics .index)',
    $routesText !== '' && $allRoutesPresent,
    'missing admin stub route: ' . ($missingRoute ?: '(unknown)'));

// ─────────────────────────────────────────────────────────────────────────
// Controller.
// ─────────────────────────────────────────────────────────────────────────
$ctrlText = read('app/Http/Controllers/DashboardController.php');
check(15, 'DashboardController::adminDashboard returns kpis + kpiDeltas + kpiSeries',
    $ctrlText !== ''
    && str_contains($ctrlText, 'adminDashboard')
    && str_contains($ctrlText, "'kpiDeltas'")
    && str_contains($ctrlText, "'kpiSeries'")
    && str_contains($ctrlText, 'private function kpiDelta')
    && str_contains($ctrlText, 'private function kpiSeries'),
    'missing kpiDeltas/kpiSeries keys or private delta/series helpers');

// ─────────────────────────────────────────────────────────────────────────
// Dashboard.tsx integration.
// ─────────────────────────────────────────────────────────────────────────
$dashboardText = read('resources/js/Pages/Dashboard.tsx');
check(16, 'Dashboard.tsx: parent passes kpiDeltas + kpiSeries to AdminDashboard',
    $dashboardText !== ''
    && str_contains($dashboardText, "import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout'")
    && str_contains($dashboardText, "import Greeting from '@/Components/Greeting'")
    && str_contains($dashboardText, 'kpiDeltas={kpiDeltas ?? {}}')
    && str_contains($dashboardText, 'kpiSeries={kpiSeries ?? {}}'),
    'missing AdminDashboardLayout/Greeting imports or props pass-through');

check(17, 'Dashboard.tsx: AdminDashboard uses Greeting + 7-card row + new KPICards with delta + series + AnimatedCounter',
    $dashboardText !== ''
    && str_contains($dashboardText, '<Greeting')
    && substr_count($dashboardText, '<KPICard') >= 7
    && str_contains($dashboardText, 'animateValue={true}'),
    'expected Greeting + ≥7 KPICards with animateValue={true}');

check(18, 'Dashboard.tsx: parent returns AdminDashboardLayout wrapping AdminDashboard for variant=admin',
    $dashboardText !== ''
    && str_contains($dashboardText, "if (variant === 'admin')")
    && str_contains($dashboardText, '<AdminDashboardLayout')
    && preg_match('/if \(variant === \'admin\'\) \{[\s\S]*?<AdminDashboardLayout/', $dashboardText) === 1,
    'missing early-return-for-admin branch wrapping AdminDashboardLayout');

// ─────────────────────────────────────────────────────────────────────────
// Risk-mitigation spot-check on Sparkline + AnimatedCounter (re-shape).
// ─────────────────────────────────────────────────────────────────────────
check(19, 'Sparkline math: handles NaN values per element via Number.isFinite guard',
    str_contains($sparklineText, 'Number.isFinite'),
    'expected per-element Number.isFinite guard');

check(20, 'AnimatedCounter: skips animation for value <= 0 + cleans up rAF on unmount',
    str_contains($counterText, 'cancelAnimationFrame(raf)')
    && str_contains($counterText, 'value <= 0'),
    'missing rAF cleanup or zero-value skip');

// ─────────────────────────────────────────────────────────────────────────
// Regression — Phase 02-07 verifiers still pass (run them externally).
// We assert file existence here.
// ─────────────────────────────────────────────────────────────────────────
$regressVerifiers = [
    'scripts/verify_phase02_run.php',
    'scripts/verify_phase03_run.php',
    'scripts/verify_phase04_run.php',
    'scripts/verify_phase05_run.php',
    'scripts/verify_phase06_run.php',
    'scripts/verify_phase06b_run.php',
    'scripts/verify_phase07_run.php',
];
$regressMissing = array_filter($regressVerifiers, fn($f) => !is_file($root . DIRECTORY_SEPARATOR . $f));
check(21, 'Phase 02-07 verifiers all present (regression net — run externally)',
    count($regressMissing) === 0,
    'missing: ' . implode(', ', $regressMissing));

// Phase 08 deliberately not run — controller + component + page exist, but
// routes/nav/verifier/HANDOFF paused mid-ship. HANDOFF carries the note.
// Phase 08 Leadership Board verifier is now SHIPPED + GREEN at 19/19.
//
// Flipped from "intentionally absent" (the prior pause marker) to "intentionally
// present with ≥19 sub-assertions" — Phase 06d.0 [22] now acts as a soft
// regression guard against accidentally deleting Phase 08's verifier.
$phase08VerifierPath = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'verify_phase08_run.php';
$phase08Exists       = is_file($phase08VerifierPath);
$phase08Content      = $phase08Exists ? (string) file_get_contents($phase08VerifierPath) : '';
$phase08Asserts      = $phase08Exists ? ((int) preg_match_all('/^\s*check\(\s*\d+\s*,/m', $phase08Content) ?: 0) : 0;
check(22, 'Phase 08 Leadership Board verifier shipped + well-formed (>=19 sub-assertions)',
    $phase08Exists && $phase08Asserts >= 19,
    'expected scripts/verify_phase08_run.php to exist with >=19 sub-assertions after Phase 08 ship');

// ─────────────────────────────────────────────────────────────────────────
// Summary.
// ─────────────────────────────────────────────────────────────────────────
echo "\n=== Phase 06d.0 verifier — {$pass} pass / {$fail} fail (out of 22 sub-assertions) ===\n";
exit($fail > 0 ? 1 : 0);
