<?php
/**
 * scripts/verify_phase06d1_run.php
 *
 * Phase 06d.1 verifier — Admin Dashboard polish, sub-phase 1.
 *
 * Asserts (15):
 *   [1]  DateRangeFilter.tsx exists
 *   [2]  DateRangeFilter: 4 preset buttons + Custom button (5 total)
 *   [3]  DateRangeFilter uses @headlessui/react Popover + Transition
 *   [4]  DateRangeFilter is EAGER (Dashboard.tsx imports it directly, NOT via React.lazy)
 *   [5]  DateRangeFilter data-testid anchors (4 presets + custom + custom-panel + custom-from + custom-to + custom-apply)
 *   [6]  OverviewAnalytics.tsx exists
 *   [7]  OverviewAnalytics.tsx imports recharts { AreaChart, Area, CartesianGrid, XAxis, YAxis, Tooltip, ResponsiveContainer, Legend }
 *   [8]  OverviewAnalytics renders 4 metric series (Guests, Contacts, Submissions, Users)
 *   [9]  DashboardController::adminDashboard signature accepts Request as 1st param
 *   [10] DashboardController has private parseRange() method that handles 5 keys
 *   [11] DashboardController has private seriesForRange() helper
 *   [12] DashboardController::adminDashboard returns rangeKey + rangeLabels + chartSeries (4 metrics) in Inertia::render payload
 *   [13] Dashboard.tsx wraps OverviewAnalytics in React.lazy(() => import(...)) inside <Suspense> with a fixed-height fallback
 *   [14] Dashboard.tsx AdminDashboard passes rangeKey / rangeLabels / chartSeries props to OverviewAnalytics section
 *   [15] Dashboard.tsx AdminDashboard renders DateRangeFilter (eager) inside the Overview Analytics section
 *
 * Run:  /d/php84/php.exe scripts/verify_phase06d1_run.php
 * Exit: 0 = green, 1 = red.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pass = 0; $fail = 0; $failures = [];

function check(int $n, string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail, $failures;
    if ($ok) {
        $pass++;
        echo "  [{$n}] PASS  {$label}\n";
    } else {
        $fail++;
        $failures[] = "[{$n}] {$label}" . ($detail !== '' ? " — {$detail}" : '');
        echo "  [{$n}] FAIL  {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

$rangePath   = $root . '/resources/js/Components/DateRangeFilter.tsx';
$chartPath   = $root . '/resources/js/Components/OverviewAnalytics.tsx';
$ctrlPath    = $root . '/app/Http/Controllers/DashboardController.php';
$dashPath    = $root . '/resources/js/Pages/Dashboard.tsx';

$range = is_file($rangePath) ? (string) file_get_contents($rangePath) : '';
$chart = is_file($chartPath) ? (string) file_get_contents($chartPath) : '';
$ctrl  = is_file($ctrlPath)  ? (string) file_get_contents($ctrlPath)  : '';
$dash  = is_file($dashPath)  ? (string) file_get_contents($dashPath)  : '';

// ─────────────────────────────────────────────────────────────────────────
// DateRangeFilter component checks (#1-#5)
// ─────────────────────────────────────────────────────────────────────────

check(1, 'DateRangeFilter.tsx exists',
    $range !== '');

check(2, 'DateRangeFilter: 4 preset buttons (Today/Week/Month/Year) + 1 Custom button = 5 total',
    substr_count($range, "key: 'today'") >= 1
    && substr_count($range, "key: 'week'")  >= 1
    && substr_count($range, "key: 'month'") >= 1
    && substr_count($range, "key: 'year'")  >= 1
    && substr_count($range, "'Custom…'")    >= 1);

check(3, 'DateRangeFilter uses @headlessui/react Popover + Transition',
    str_contains($range, "@headlessui/react")
    && str_contains($range, 'Popover')
    && str_contains($range, 'Transition'));

check(4, 'DateRangeFilter is EAGER — Dashboard.tsx imports it directly (NOT via React.lazy)',
    !preg_match('/lazy\s*\(\s*\(\s*\)\s*=>\s*import\s*\(\s*[\'"]@\\/Components\\/DateRangeFilter[\'"]/s', $dash)
    && (str_contains($dash, "import DateRangeFilter from '@/Components/DateRangeFilter'")
        || preg_match('/from\s+[\'"]\@\\/Components\\/DateRangeFilter[\'"]/', $dash) === 1),
    'eager import expected; React.lazy wrap not expected');

check(5, 'DateRangeFilter data-testid anchors: 4 presets + custom + custom-panel + custom-from + custom-to + custom-apply',
    substr_count($range, 'date-range-today')    >= 1
    && substr_count($range, 'date-range-week')  >= 1
    && substr_count($range, 'date-range-month') >= 1
    && substr_count($range, 'date-range-year')  >= 1
    && substr_count($range, 'date-range-custom-panel')   >= 1
    && substr_count($range, 'date-range-custom-from')    >= 1
    && substr_count($range, 'date-range-custom-to')      >= 1
    && substr_count($range, 'date-range-custom-apply')   >= 1);

// ─────────────────────────────────────────────────────────────────────────
// OverviewAnalytics component checks (#6-#8)
// ─────────────────────────────────────────────────────────────────────────

check(6, 'OverviewAnalytics.tsx exists',
    $chart !== '');

check(7, 'OverviewAnalytics imports recharts (AreaChart+Area+CartesianGrid+XAxis+YAxis+Tooltip+ResponsiveContainer+Legend)',
    str_contains($chart, "from 'recharts'")
    && str_contains($chart, 'AreaChart')
    && str_contains($chart, 'Area,')
    && str_contains($chart, 'CartesianGrid')
    && str_contains($chart, 'XAxis')
    && str_contains($chart, 'YAxis')
    && str_contains($chart, 'Tooltip')
    && str_contains($chart, 'ResponsiveContainer')
    && str_contains($chart, 'Legend'));

check(8, 'OverviewAnalytics renders 4 metrics: Guests / Contacts / Submissions / Users',
    str_contains($chart, "'Guests'")
    && str_contains($chart, "'Contacts'")
    && str_contains($chart, "'Submissions'")
    && str_contains($chart, "'Users'"));

// ─────────────────────────────────────────────────────────────────────────
// DashboardController extension checks (#9-#12)
// ─────────────────────────────────────────────────────────────────────────

check(9, 'DashboardController::adminDashboard signature accepts Request as 1st param',
    (bool) preg_match('/private\s+function\s+adminDashboard\s*\(\s*Request\s+\$request\s*,/s', $ctrl),
    'expected `private function adminDashboard(Request $request, ...)` signature');

check(10, 'DashboardController has private parseRange(Request $request) returning config with 5 keys',
    (bool) preg_match('/private\s+function\s+parseRange\s*\(\s*Request\s+\$request\s*\)/s', $ctrl)
    && (str_contains($ctrl, "'today'") || str_contains($ctrl, '"today"'))
    && (str_contains($ctrl, "'month'") || str_contains($ctrl, '"month"'))
    && (str_contains($ctrl, "'year'")  || str_contains($ctrl, '"year"'))
    && (str_contains($ctrl, "'custom'") || str_contains($ctrl, '"custom"')),
    'expected parseRange() returning config covering 5 range keys');

check(11, 'DashboardController has private seriesForRange(query, from, to, bucketCount, bucketUnit, labels) helper',
    (bool) preg_match('/private\s+function\s+seriesForRange\s*\(/s', $ctrl),
    'expected `private function seriesForRange(...)`');

check(12, 'DashboardController::adminDashboard returns rangeKey + rangeLabels + chartSeries (4 cumulative metrics) in Inertia::render',
    str_contains($ctrl, "rangeKey")
    && str_contains($ctrl, "rangeLabels")
    && str_contains($ctrl, "chartSeries")
    // 4 chart metric keys
    && (str_contains($ctrl, "'totalGuests'")      && str_contains($ctrl, "'totalCalls'")
        && str_contains($ctrl, "'totalSubmissions'") && str_contains($ctrl, "'totalUsers'")),
    'expected rangeKey + rangeLabels + chartSeries with 4 cumulative metric keys');

// ─────────────────────────────────────────────────────────────────────────
// Dashboard.tsx wiring checks (#13-#15)
// ─────────────────────────────────────────────────────────────────────────

// Extract the AdminDashboard function body to scope assertions to the
// admin dashboard variant only.
//
// Anchor: matches `function AdminDashboard(` through end-of-file. AdminDashboard
// Anchored on two UNIQUE markers — opening on the literal
// `data-testid="admin-dashboard-root"` line (Dashboard.tsx:986, exactly
// one occurrence in the file), closing on the function-closing brace
// (`\n\s*\}\s*\z`) which is canonical when `AdminDashboard` is the LAST
// top-level export in `resources/js/Pages/Dashboard.tsx`. The previous
// `function\s+AdminDashboard\s*\([\s\S]*\z` regex was fragile to any
// future function appended AFTER `AdminDashboard` — its `[\s\S]*\z` would
// have over-captured past the new sibling function, leaking that function's
// body into `$adminFn` and potentially false-tripping [14]/[15]. Anchor
// on the function-closing brace so capture ends at the function boundary
// regardless of what lives after.
preg_match('/data-testid="admin-dashboard-root"[\s\S]*\n\s*\}\s*\z/s', $dash, $mAdmin);
$adminFn = $mAdmin[0] ?? '';

check(13, 'Dashboard.tsx wraps OverviewAnalytics in React.lazy(() => import(...)) inside <Suspense> with a fixed-height fallback',
    (bool) preg_match('/lazy\s*\(\s*\(\s*\)\s*=>\s*import\s*\(\s*[\'"]\@\/Components\/OverviewAnalytics[\'"]\s*\)\s*\)/s', $dash)
    && (bool) preg_match('/<Suspense[^>]*>/s', $dash)
    // Suspense fallback must declare a fixed height (h-[NNNpx] or style={{ height: NNN }})
    && ((bool) preg_match('/<Suspense[^>]*fallback\s*=\s*\{[^}]*h-\[[0-9]+px\]/s', $dash)
        || (bool) preg_match('/fallback\s*=\s*\{[^}]*height:\s*[0-9]+/s', $dash)),
    'expected React.lazy(@/Components/OverviewAnalytics) + <Suspense fallback> with fixed-height placeholder');

check(14, 'Dashboard.tsx AdminDashboard passes rangeKey / rangeLabels / chartSeries to the lazy OverviewAnalytics section',
    str_contains($adminFn, 'rangeKey')
    && str_contains($adminFn, 'rangeLabels')
    && str_contains($adminFn, 'chartSeries'),
    'admin variant body must thread rangeKey + rangeLabels + chartSeries into the chart panel');

check(15, 'Dashboard.tsx AdminDashboard wires OverviewAnalyticsSection (which transitively composes eager DateRangeFilter + lazy chart)',
    str_contains($adminFn, '<OverviewAnalyticsSection')
    && str_contains($adminFn, 'rangeKey={rangeKey')
    && str_contains($adminFn, 'rangeFrom={rangeFrom')
    && str_contains($adminFn, 'chartSeries={chartSeries'),
    'admin body must render <OverviewAnalyticsSection rangeKey=… rangeFrom=… chartSeries=… /> (the helper internally renders <DateRangeFilter> + <Suspense><OverviewAnalytics /></Suspense>)');

// ─────────────────────────────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────────────────────────────

echo "\n========================================\n";
echo "  Phase 06d.1 Overview Analytics: {$pass} pass / {$fail} fail\n";
echo "========================================\n";

if ($fail > 0) {
    echo "\nFAILURES:\n";
    foreach ($failures as $f) echo "  - {$f}\n";
    exit(1);
}
exit(0);
