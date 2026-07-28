<?php
/**
 * Phase 11b verifier -- Reports + Audit polish round.
 *
 * Resolves HANDOFF Section 1 Phase 11 row footer deferred items:
 *   (a) /api/reports/audit JSON endpoint per spec section 3.
 *   (b) By Join When Donut chart (FirstTimer / NewMember / OldMember) per spec section 1.
 *   (c) Officer Performance top-10 table with conversion rate per spec section 1.
 *   (d) Audit side-panel before/after diff viewer per spec section 2.
 *
 * Style note: str_contains-only assertions (per Phase 09b stable pattern).
 *
 * Run: php scripts/verify_phase11b_run.php
 * Expected: 15 pass / 0 fail.
 *
 * ChartCard testId-vs-data-testid rule (do not regress):
 *   The shared `ChartCard` wrapper component receives a `testId` PROP, not a `data-testid`
 *   ATTRIBUTE -- it renders `<section ... data-testid={testId}>`. Source needles that look for a
 *   wrapped chart's identifier MUST match the prop string the caller passes (e.g.
 *   `testId="card-joinwhen-chart"`), NOT the rendered DOM attribute (`data-testid="..."`),
 *   because this verifier scans SOURCE text, not rendered HTML. Officer Performance uses a raw
 *   `<section data-testid="card-officer-performance">` (no ChartCard wrapper), so that one
 *   keeps the literal `data-testid=` needle.
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

$reportsCtrlPath   = $root . '/app/Http/Controllers/ReportsController.php';
$auditCtrlPath     = $root . '/app/Http/Controllers/AuditLogController.php';
$pagesReportsPath  = $root . '/resources/js/Pages/Reports/Index.tsx';
$pagesAuditPath    = $root . '/resources/js/Pages/Audit/Index.tsx';
$routesPath        = $root . '/routes/web.php';

$reportsSrc      = is_file($reportsCtrlPath) ? file_get_contents($reportsCtrlPath) : '';
$auditSrc        = is_file($auditCtrlPath) ? file_get_contents($auditCtrlPath) : '';
$pagesReportsSrc = is_file($pagesReportsPath) ? file_get_contents($pagesReportsPath) : '';
$pagesAuditSrc   = is_file($pagesAuditPath) ? file_get_contents($pagesAuditPath) : '';
$routesSrc       = is_file($routesPath) ? file_get_contents($routesPath) : '';

// ---------------------------------------------------------------------------
// [1] self-syntax -- verifier file parses cleanly.
// ---------------------------------------------------------------------------
$tmpLint = tempnam(sys_get_temp_dir(), 'p11b_lint_');
file_put_contents($tmpLint, file_get_contents(__FILE__));
$lintOut = shell_exec(escapeshellcmd(PHP_BINARY) . ' -l ' . escapeshellarg($tmpLint) . ' 2>&1');
unlink($tmpLint);
check(1, 'verify_phase11b_run.php parses cleanly (php -l)',
    is_file(__FILE__) && str_contains((string) $lintOut, 'No syntax errors detected'),
    'php -l reports "No syntax errors detected"');

// ---------------------------------------------------------------------------
// [2] AuditLogController imports JsonResponse + has apiIndex method signature.
// ---------------------------------------------------------------------------
check(2, 'AuditLogController imports JsonResponse + has public function apiIndex(Request): JsonResponse',
    str_contains($auditSrc, 'use Illuminate\Http\JsonResponse')
    && str_contains($auditSrc, 'public function apiIndex(Request $request): JsonResponse'),
    'use Illuminate\\Http\\JsonResponse + public function apiIndex(Request $request): JsonResponse');

// ---------------------------------------------------------------------------
// [3] apiIndex returns response()->json() (not Inertia::render).
// ---------------------------------------------------------------------------
check(3, 'AuditLogController::apiIndex() returns response()->json([...]) for scriptable clients',
    str_contains($auditSrc, 'response()->json('),
    'apiIndex uses response()->json() so curl/future-mobile clients can consume the audit list');

// ---------------------------------------------------------------------------
// [4] apiIndex applies limit(500) + the same 3 filters as index() (causer_id / subject_type / subject_id).
// ---------------------------------------------------------------------------
check(4, 'AuditLogController::apiIndex() applies ->limit(500) + 3 filters (causer_id + subject_type + subject_id)',
    str_contains($auditSrc, '->limit(500)')
    && str_contains($auditSrc, "->where('causer_id'")
    && str_contains($auditSrc, "->where('subject_type'")
    && str_contains($auditSrc, "->where('subject_id'"),
    'must apply ->limit(500) + filter where(causer_id, $actor) + where(subject_type, ...) + where(subject_id, $entityId)');

// ---------------------------------------------------------------------------
// [5] routes/web.php registers GET /api/reports/audit + named route api.reports.audit.
// ---------------------------------------------------------------------------
check(5, "routes/web.php registers GET /api/reports/audit (AuditLogController@apiIndex) with name 'api.reports.audit'",
    str_contains($routesSrc, 'Route::get')
    && str_contains($routesSrc, '/api/reports/audit')
    && str_contains($routesSrc, "'apiIndex'")
    && str_contains($routesSrc, "'api.reports.audit'"),
    "Route::get('/api/reports/audit', [AuditLogController::class, 'apiIndex'])->name('api.reports.audit')");

// ---------------------------------------------------------------------------
// [6] ReportsController has byJoinWhen query (SELECT join_when + COUNT + groupBy).
// ---------------------------------------------------------------------------
check(6, 'ReportsController queries Guest::selectRaw(join_when, COUNT(*) as cnt)->groupBy(join_when) for By Join When donut',
    str_contains($reportsSrc, "'join_when'")
    && str_contains($reportsSrc, "'byJoinWhen'")
    && str_contains($reportsSrc, "groupBy('join_when')"),
    'must aggregate join_when + COUNT(*) -> groupBy(join_when) for Donut chart payload');

// ---------------------------------------------------------------------------
// [7] ReportsController officerPerformance query JOINs users table for officer name.
// ---------------------------------------------------------------------------
check(7, 'ReportsController officerPerformance JOINs users on guests.follow_officer_id for officer name',
    str_contains($reportsSrc, "'officerPerformance'")
    && str_contains($reportsSrc, "join('users'")
    && str_contains($reportsSrc, "'guests.follow_officer_id'")
    && str_contains($reportsSrc, "'users.id'"),
    'officerPerformance must join users on guests.follow_officer_id = users.id for officer name lookup');

// ---------------------------------------------------------------------------
// [8] officerPerformance selects COUNT(guests.id) as total + SUM(guests.visited) as visited.
// ---------------------------------------------------------------------------
check(8, 'ReportsController officerPerformance SELECTs COUNT(guests.id) as total + COALESCE(SUM(guests.visited)) as visited',
    str_contains($reportsSrc, 'COUNT(guests.id) as total')
    && str_contains($reportsSrc, 'SUM(guests.visited)')
    && str_contains($reportsSrc, 'COALESCE'),
    'officerPerformance must select COUNT(guests.id) as total + COALESCE(SUM(guests.visited), 0) as visited');

// ---------------------------------------------------------------------------
// [9] officerPerformance: conversion_rate + orderByDesc + limit 10.
// ---------------------------------------------------------------------------
check(9, 'ReportsController officerPerformance: conversion_rate round + orderByDesc total + limit 10',
    str_contains($reportsSrc, '/ $row->total) * 100')
    && str_contains($reportsSrc, 'orderByDesc')
    && str_contains($reportsSrc, '->limit(10)'),
    'must compute conversion_rate = round(($row->visited / $row->total) * 100, 1) + ->orderByDesc(\'total\') + ->limit(10)');

// ---------------------------------------------------------------------------
// [10] Reports/Index.tsx imports PieChart + Pie + Cell from recharts.
// ---------------------------------------------------------------------------
check(10, 'Reports/Index.tsx imports PieChart + Pie + Cell from recharts',
    str_contains($pagesReportsSrc, "from 'recharts'")
    && str_contains($pagesReportsSrc, 'PieChart')
    && str_contains($pagesReportsSrc, '<Pie')
    && str_contains($pagesReportsSrc, '<Cell'),
    'must import { PieChart, Pie, Cell } from recharts and render <PieChart><Pie><Cell /></Pie></PieChart>');

// ---------------------------------------------------------------------------
// [11] Reports/Index.tsx renders By Join When card + <PieChart> (donut shape).
// ---------------------------------------------------------------------------
check(11, 'Reports/Index.tsx renders By Join When card via <ChartCard testId="card-joinwhen-chart"> + <PieChart>',
    str_contains($pagesReportsSrc, 'By Join When')
    && str_contains($pagesReportsSrc, '<PieChart>')
    && str_contains($pagesReportsSrc, 'testId="card-joinwhen-chart"'),
    'must render "By Join When" + <PieChart> + <ChartCard testId="card-joinwhen-chart"> (ChartCard wraps data-testid={testId})');

// ---------------------------------------------------------------------------
// [12] Reports/Index.tsx renders Officer Performance top-10 table (Assigned / Visited / Conversion %).
// ---------------------------------------------------------------------------
check(12, 'Reports/Index.tsx renders Officer Performance top-10 table with conversion_rate column',
    str_contains($pagesReportsSrc, 'Officer Performance')
    && str_contains($pagesReportsSrc, 'conversion_rate')
    && str_contains($pagesReportsSrc, 'data-testid="card-officer-performance"')
    && str_contains($pagesReportsSrc, 'officerPerformance'),
    'must render "Officer Performance" card + conversion_rate column + officerPerformance prop + data-testid');

// ---------------------------------------------------------------------------
// [13] Audit/Index.tsx has DiffViewer component using properties.old + properties.attributes shape.
// ---------------------------------------------------------------------------
check(13, 'Audit/Index.tsx DiffViewer handles Spatie Activity properties shape (old / attributes)',
    str_contains($pagesAuditSrc, 'DiffViewer')
    && str_contains($pagesAuditSrc, 'properties.old')
    && str_contains($pagesAuditSrc, 'properties.attributes'),
    'must define DiffViewer component reading properties.old + properties.attributes (Spatie Activity diff shape)');

// ---------------------------------------------------------------------------
// [14] Audit/Index.tsx DiffViewer renders <del> (red) for old + <ins> (green) for new values.
// ---------------------------------------------------------------------------
check(14, 'Audit/Index.tsx DiffViewer renders <del> for old + <ins> for new values (red + green color semantics)',
    str_contains($pagesAuditSrc, '<del')
    && str_contains($pagesAuditSrc, '<ins')
    && str_contains($pagesAuditSrc, 'no-underline'),
    'must render <del> for old values + <ins class="...no-underline"> for new values');

// ---------------------------------------------------------------------------
// [15] Audit/Index.tsx DiffViewer wrapped in data-testid="audit-diff-viewer" + JSON.stringify fallback.
// ---------------------------------------------------------------------------
check(15, 'Audit/Index.tsx DiffViewer wrapped in data-testid="audit-diff-viewer" + has JSON.stringify fallback',
    str_contains($pagesAuditSrc, 'data-testid="audit-diff-viewer"')
    && str_contains($pagesAuditSrc, 'JSON.stringify(properties'),
    'DiffViewer wrapper data-testid="audit-diff-viewer" + JSON.stringify fallback for unknown properties shape');

// ---------------------------------------------------------------------------

echo "\nPhase 11b verifier: {$pass} pass / {$fail} fail\n";
if ($fail > 0) {
    echo "\nFailures:\n";
    foreach ($failed as $f) {
        echo "  - {$f}\n";
    }
}
exit($fail === 0 ? 0 : 1);
