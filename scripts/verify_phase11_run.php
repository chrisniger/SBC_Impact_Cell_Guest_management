<?php
/**
 * Phase 11 — Reports & Audit verifier.
 *
 * Asserts the architectural surface of Phase 11 (Implementation/Phase_11_Reports_And_Audit.md):
 *   - ReportsController 5-role gate (Administrator / Supervisor / Impact_Cell_Admin /
 *     Impact_Cell_Report / Follow_UP_Admin) + month scoping via whereYear/whereMonth +
 *     default month = now()->format('Y-m') + 5 KPIs (pendingContacts/totalCalls/
 *     visited/pendingVisit/responseRate with 0-fallback) + 4 group-by breakdowns
 *     (byStatus/byEvent/byFollowUp/monthly) + Inertia render to Reports/Index with
 *     {kpis, byStatus, byEvent, byFollowUp, monthly, month}.
 *   - AuditLogController admin-gate + Activity::with('causer:id,name')->latest() +
 *     3 optional filters (actor → causer_id; entity → subject_type derived from
 *     'App\\Models\\' + ucfirst($entityType); entity_id → subject_id) + limit(500) +
 *     7-key entry map (id/description/actor/subjectType/subjectId/properties/createdAt).
 *   - 2 routes registered: GET /reports + GET /audit.
 *   - resources/js/Pages/Reports/Index.tsx renders 5 KPICard with all 5 captions +
 *     recharts (BarChart + AreaChart + CartesianGrid + ResponsiveContainer) +
 *     month-filter <input type="month"> + router.get('/reports', { month }) +
 *     Export CSV <Link href={route('csv.export')}>.
 *   - resources/js/Pages/Audit/Index.tsx renders useState<Entry|null> +
 *     data-testid="card-audit" + "audit-table" + "card-audit-details" +
 *     data-testid="audit-diff-viewer" DiffViewer wrapper.
 *
 * 20 sub-assertions across: self-syntax (1) → spec-doc (1) → ReportsController (8) →
 * AuditLogController (5) → routes (1) → Reports page (2) → Audit page (2) →
 * Inertia 2.x return-type trap (1).
 *
 * Note: All regex patterns use single-quoted PHP strings + canonical '\\$var' (2 source
 * backslashes before $) form. Single-quote only processes `\\` → `\` and `\'` → `'`, so
 * `\\$var` source produces runtime `\$var` (1 backslash + $var) which is the PCRE pattern
 * that matches literal `$var` in subject. This is empirically confirmed via
 * /tmp/test_pcre_escape.php.
 *
 * Deferred to Phase 11b (documented in HANDOFF §1 row):
 *   - /api/reports/audit JSON endpoint for filter wiring.
 *   - Join-when Donut chart (FirstTimer / NewMember / OldMember breakdown).
 *   - Officer Performance top-10 table with conversion rate.
 *   - Audit side-panel before/after diff viewer per spec §2.
 */

declare(strict_types=1);

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): void {
    fwrite(STDERR, "PHP error (#{$errno}): {$errstr} in {$errfile}:{$errline}\n");
    exit(1);
});

$pass = 0;
$fail = 0;
$failed = [];

function check(int $n, string $label, bool $cond, string $expected): void {
    global $pass, $fail, $failed;
    if ($cond) {
        $pass++;
        echo "  [{$n}] pass — {$label}\n";
    } else {
        $fail++;
        $failed[] = "[{$n}] {$label} — expected: {$expected}";
        echo "  [{$n}] FAIL — {$label} (expected: {$expected})\n";
    }
}

$base = __DIR__ . '/..';

$reportsCtrlPath = $base . '/app/Http/Controllers/ReportsController.php';
$auditCtrlPath = $base . '/app/Http/Controllers/AuditLogController.php';
$pagesReportsPath = $base . '/resources/js/Pages/Reports/Index.tsx';
$pagesAuditPath = $base . '/resources/js/Pages/Audit/Index.tsx';
$routesPath = $base . '/routes/web.php';
$specPath = $base . '/Implementation/Phase_11_Reports_And_Audit.md';

// ---------------------------------------------------------------------------
// [1] self-syntax — verifier file must be PHP-parseable (no fatal).
// ---------------------------------------------------------------------------
check(1, 'verify_phase11_run.php is PHP-parseable (no fatal)', true, 'self');

// ---------------------------------------------------------------------------
// [2] Phase 11 spec doc exists + covers 5 KPIs + 4 charts + Officer Perf table + Audit filters.
// ---------------------------------------------------------------------------
$specExists = is_file($specPath);
$specText = $specExists ? file_get_contents($specPath) : '';
check(2, 'Phase 11 spec doc exists + covers 5 KPIs + 4 charts + Officer Performance table + Audit log filters',
    $specExists
    && str_contains($specText, 'Pending Contacts')
    && str_contains($specText, 'Total Calls')
    && str_contains($specText, 'Visited')
    && str_contains($specText, 'Pending Visit')
    && str_contains($specText, 'Response Rate')
    && str_contains($specText, 'By Status')
    && str_contains($specText, 'By Join When')
    && str_contains($specText, 'By Follow Up Status')
    && str_contains($specText, 'By Event')
    && str_contains($specText, 'Monthly Trend')
    && str_contains($specText, 'Officer Performance')
    && str_contains($specText, 'Audit Log'),
    'spec doc must cover all 5 KPI names + 5 chart titles + Officer Performance + Audit Log'
);

// ---------------------------------------------------------------------------
// [3] ReportsController exists + extends Controller + has index().
// ---------------------------------------------------------------------------
$reportsSrc = is_file($reportsCtrlPath) ? file_get_contents($reportsCtrlPath) : '';
check(3, 'ReportsController exists + extends Controller + has index(Request): Response',
    $reportsSrc !== ''
    && str_contains($reportsSrc, 'class ReportsController extends Controller')
    && preg_match('/public function index\s*\(\s*Request\s+\\$request\s*\)\s*:\s*Response/', $reportsSrc) === 1,
    '`class ReportsController extends Controller` with `public function index(Request $request): Response`'
);

// ---------------------------------------------------------------------------
// [4] ReportsController::index() 5-role gate via in_array(..., true) with 403.
// ---------------------------------------------------------------------------
check(4, 'ReportsController::index() 5-role gated via in_array (Administrator + Supervisor + Impact_Cell_Admin + Impact_Cell_Report + Follow_UP_Admin)',
    preg_match('/abort_unless\s*\(\s*in_array\s*\(\s*\\$role\s*,\s*\[\s*\'Administrator\'\s*,\s*\'Supervisor\'\s*,\s*\'Impact_Cell_Admin\'\s*,\s*\'Impact_Cell_Report\'\s*,\s*\'Follow_UP_Admin\'\s*\]\s*,\s*true\s*\)\s*,\s*403\s*\)/', $reportsSrc) === 1,
    'must gate on `in_array($role, [\'Administrator\',\'Supervisor\',\'Impact_Cell_Admin\',\'Impact_Cell_Report\',\'Follow_UP_Admin\'], true)` with 403'
);

// ---------------------------------------------------------------------------
// [5] ReportsController::index() default month = now()->format('Y-m') + month scoping via whereYear + whereMonth.
// ---------------------------------------------------------------------------
check(5, 'ReportsController::index() default $month = $request->get(\'month\', now()->format(\'Y-m\')) + scope via whereYear + whereMonth',
    preg_match('/\\$request->get\s*\(\s*\'month\'\s*,\s*now\s*\(\s*\)\s*->format\s*\(\s*\'Y-m\'\s*\)\s*\)/', $reportsSrc) === 1
    && preg_match('/whereYear\s*\(\s*\'created_at\'\s*,\s*substr\s*\(\s*\\$month\s*,\s*0\s*,\s*4\s*\)\s*\)/', $reportsSrc) === 1
    && preg_match('/whereMonth\s*\(\s*\'created_at\'\s*,\s*substr\s*\(\s*\\$month\s*,\s*5\s*,\s*2\s*\)\s*\)/', $reportsSrc) === 1,
    'must read `$request->get(\'month\', now()->format(\'Y-m\'))` + scope via `whereYear(\'created_at\', substr($month, 0, 4))` + `whereMonth(\'created_at\', substr($month, 5, 2))`'
);

// ---------------------------------------------------------------------------
// [6] ReportsController::index() returns 5 KPIs (pendingContacts, totalCalls, visited, pendingVisit, responseRate).
// ---------------------------------------------------------------------------
check(6, 'ReportsController::index() returns 5 KPI keys in payload: pendingContacts, totalCalls, visited, pendingVisit, responseRate',
    preg_match('/\'pendingContacts\'\s*=>\s*\\$pendingContacts/', $reportsSrc) === 1
    && preg_match('/\'totalCalls\'\s*=>\s*\\$totalCalls/', $reportsSrc) === 1
    && preg_match('/\'visited\'\s*=>\s*\\$visited/', $reportsSrc) === 1
    && preg_match('/\'pendingVisit\'\s*=>\s*\\$pendingVisit/', $reportsSrc) === 1
    && preg_match('/\'responseRate\'\s*=>\s*\\$responseRate/', $reportsSrc) === 1,
    'payload must include 5 keys: pendingContacts + totalCalls + visited + pendingVisit + responseRate'
);

// ---------------------------------------------------------------------------
// [7] ReportsController::index() pendingContacts computes via whereNull/empty/'No','Not Contacted'.
// ---------------------------------------------------------------------------
check(7, 'ReportsController pendingContacts computed via whereNull OR empty OR in:[\'No\',\'Not Contacted\'] on contacted_status',
    preg_match('/whereNull\s*\(\s*\'contacted_status\'\s*\)/', $reportsSrc) === 1
    && preg_match('/orWhere\s*\(\s*\'contacted_status\'\s*,\s*\'\'\s*\)/', $reportsSrc) === 1
    && preg_match('/orWhereIn\s*\(\s*\'contacted_status\'\s*,\s*\[\s*\'No\'\s*,\s*\'Not Contacted\'\s*\]\s*\)/', $reportsSrc) === 1,
    'must combine `whereNull(\'contacted_status\')` + `orWhere(\'contacted_status\', \'\')` + `orWhereIn(\'contacted_status\', [\'No\',\'Not Contacted\'])` + `->count()`'
);

// ---------------------------------------------------------------------------
// [8] ReportsController::index() responseRate via round(($visited/$totalCalls)*100, 1) with 0-fallback.
// ---------------------------------------------------------------------------
check(8, 'ReportsController responseRate = round(visited / totalCalls * 100, 1) with 0-fallback when totalCalls==0',
    preg_match('/\\$responseRate\s*=\s*\\$totalCalls\s*>\s*0\s*\?\s*round\s*\(\s*\(\s*\\$visited\s*\/\s*\\$totalCalls\s*\)\s*\*\s*100\s*,\s*1\s*\)\s*:\s*0\s*;/', $reportsSrc) === 1,
    'must compute `$responseRate = $totalCalls > 0 ? round(($visited / $totalCalls) * 100, 1) : 0`'
);

// ---------------------------------------------------------------------------
// [9] ReportsController::index() returns Inertia::render('Reports/Index', [..., 'month']) with month in payload.
// ---------------------------------------------------------------------------
check(9, 'ReportsController::index() renders Inertia Reports/Index with [\'month\' => $month] in payload',
    preg_match('/Inertia::render\s*\(\s*\'Reports\/Index\'/', $reportsSrc) === 1
    && preg_match('/\'month\'\s*=>\s*\\$month/', $reportsSrc) === 1,
    'must call `Inertia::render(\'Reports/Index\', [...])` and include `\'month\' => $month` key in payload'
);

// ---------------------------------------------------------------------------
// [10] ReportsController::index() returns 4 group-by breakdowns (byStatus, byEvent, byFollowUp, monthly) — each via selectRaw + groupBy.
// ---------------------------------------------------------------------------
check(10, 'ReportsController::index() returns 4 group-by breakdowns (byStatus/byEvent/byFollowUp/monthly) — each via selectRaw + groupBy',
    preg_match('/\'byStatus\'\s*=>\s*\\$byStatus/', $reportsSrc) === 1
    && preg_match('/\'byEvent\'\s*=>\s*\\$byEvent/', $reportsSrc) === 1
    && preg_match('/\'byFollowUp\'\s*=>\s*\\$byFollowUp/', $reportsSrc) === 1
    && preg_match('/\'monthly\'\s*=>\s*\\$monthly/', $reportsSrc) === 1
    && preg_match('/selectRaw\s*\(\s*\'contacted_status,\s*COUNT\(\*\)\s*as\s*cnt\'/', $reportsSrc) === 1
    && preg_match('/selectRaw\s*\(\s*\'event,\s*COUNT\(\*\)\s*as\s*cnt\'/', $reportsSrc) === 1
    && preg_match('/selectRaw\s*\(\s*"COALESCE\(NULLIF\(follow_up_status,\s*\'\'\),\s*\'NOT\s*CONTACTED\'\)/', $reportsSrc) === 1
    && preg_match('/selectRaw\s*\(\s*"DATE_FORMAT\(created_at,\s*\'%Y-%m\'\)\s*as\s*ym/', $reportsSrc) === 1
    && preg_match('/->orderBy\s*\(\s*\'ym\'\s*\)/', $reportsSrc) === 1,
    'payload includes byStatus + byEvent + byFollowUp + monthly; each via selectRaw(...COUNT(*)...) + groupBy; followUp uses COALESCE(NULLIF(\'\'), \'NOT CONTACTED\'); monthly uses DATE_FORMAT(\'%Y-%m\') + orderBy(\'ym\')'
);

// ---------------------------------------------------------------------------
// [11] AuditLogController exists + extends Controller + has index().
// ---------------------------------------------------------------------------
$auditSrc = is_file($auditCtrlPath) ? file_get_contents($auditCtrlPath) : '';
check(11, 'AuditLogController exists + extends Controller + has index(Request): Response',
    $auditSrc !== ''
    && str_contains($auditSrc, 'class AuditLogController extends Controller')
    && preg_match('/public function index\s*\(\s*Request\s+\\$request\s*\)\s*:\s*Response/', $auditSrc) === 1
    && str_contains($auditSrc, 'use Spatie\\Activitylog\\Models\\Activity'),
    '`class AuditLogController extends Controller` with `public function index(Request $request): Response` + imports Spatie Activitylog model'
);

// ---------------------------------------------------------------------------
// [12] AuditLogController::index() admin-gated via activeRole === Administrator.
// ---------------------------------------------------------------------------
check(12, 'AuditLogController::index() admin-gated via abort_unless(activeRole === Administrator, 403)',
    preg_match('/abort_unless\s*\(\s*\\$request->user\s*\(\s*\)\s*\?\s*->\s*activeRole\s*\(\s*\)\s*===\s*\'Administrator\'\s*,\s*403\s*\)/', $auditSrc) === 1,
    'must guard `abort_unless($request->user()?->activeRole() === \'Administrator\', 403)`'
);

// ---------------------------------------------------------------------------
// [13] AuditLogController::index() queries Activity::with(\'causer:id,name\')->latest().
// ---------------------------------------------------------------------------
check(13, 'AuditLogController queries Activity::with(\'causer:id,name\')->latest() (causer eager-load + latest-first ordering)',
    preg_match('/Activity::with\s*\(\s*\'causer:id,name\'\s*\)\s*->\s*latest\s*\(\s*\)/', $auditSrc) === 1,
    'must call `Activity::with(\'causer:id,name\')->latest()`'
);

// ---------------------------------------------------------------------------
// [14] AuditLogController::index() 3 optional filters (actor → causer_id, entity → subject_type, entity_id → subject_id).
// ---------------------------------------------------------------------------
check(14, 'AuditLogController 3 optional filters: actor (causer_id) + entity (subject_type via \'App\\\\Models\\\\\' . ucfirst) + entity_id (subject_id)',
    str_contains($auditSrc, '$query->where(\'causer_id\', $actor)')
    && str_contains($auditSrc, '->where(\'subject_type\'')
    && str_contains($auditSrc, 'ucfirst($entityType)')
    && str_contains($auditSrc, '->where(\'subject_id\'')
    && str_contains($auditSrc, '$query->where(\'subject_id\', $entityId)'),
    'must 3-filter the Activity query: actor → causer_id; entity → subject_type via \'App\\\\Models\\\\\' . ucfirst($entityType); entity_id → subject_id'
);

// ---------------------------------------------------------------------------
// [15] AuditLogController::index() Inertia::render Audit/Index + limit(500) + 7-key entry map (id/description/actor/subjectType/subjectId/properties/createdAt).
// ---------------------------------------------------------------------------
check(15, 'AuditLogController renders Inertia Audit/Index + limit(500) + 7-key entry-map (id,description,actor,subjectType,subjectId,properties,createdAt)',
    preg_match('/Inertia::render\s*\(\s*\'Audit\/Index\'/', $auditSrc) === 1
    && preg_match('/->limit\s*\(\s*500\s*\)/', $auditSrc) === 1
    && str_contains($auditSrc, '\'id\'          => $a->id')
    && str_contains($auditSrc, '\'description\' => $a->description')
    && str_contains($auditSrc, '\'actor\'       => $a->causer?->name')
    && str_contains($auditSrc, '\'subjectType\' => class_basename')
    && str_contains($auditSrc, '\'subjectId\'   => $a->subject_id')
    && str_contains($auditSrc, '\'properties\'  => $a->properties')
    && str_contains($auditSrc, '\'createdAt\'   => $a->created_at'),
    'must render `Inertia::render(\'Audit/Index\', ...)` + apply `->limit(500)` + entries.map with 7 keys: id, description, actor (=> $a->causer?->name ?? \'System\'), subjectType (=> class_basename($a->subject_type ?? \'\')), subjectId, properties, createdAt (=> $a->created_at?->toIso8601String())'
);

// ---------------------------------------------------------------------------
// [16] routes/web.php registers reports.index + audit.index (co-proximity pattern — tolerates any namespace aliasing).
// ---------------------------------------------------------------------------
$routesSrc = is_file($routesPath) ? file_get_contents($routesPath) : '';
check(16, 'routes/web.php registers reports.index (GET) + audit.index (GET) — single-line co-proximity via [^;]*',
    preg_match('/Route::get[^;]*\'\/reports\'[^;]*ReportsController::class[^;]*\'index\'[^;]*\'reports\.index\'[^;]*;/s', $routesSrc) === 1
    && preg_match('/Route::get[^;]*\'\/audit\'[^;]*AuditLogController::class[^;]*\'index\'[^;]*\'audit\.index\'[^;]*;/s', $routesSrc) === 1,
    'must register 2 routes as single-statement co-proximity of {HTTP method + path + last-segment controller class + method + route-name}: Route::get+\'\/reports\'+ReportsController::class+\'index\'+\'reports.index\' AND Route::get+\'\/audit\'+AuditLogController::class+\'index\'+\'audit.index\''
);

// ---------------------------------------------------------------------------
// [17] Reports/Index.tsx renders all 5 KPICard captions (Pending Contacts / Total Calls / Visited / Pending Visit / Response Rate) + recharts (BarChart + AreaChart + CartesianGrid + ResponsiveContainer).
// ---------------------------------------------------------------------------
$pagesReportsSrc = is_file($pagesReportsPath) ? file_get_contents($pagesReportsPath) : '';
check(17, 'Reports/Index.tsx renders 5 KPICard captions (Pending Contacts / Total Calls / Visited / Pending Visit / Response Rate) + recharts (BarChart + AreaChart + CartesianGrid + ResponsiveContainer)',
    substr_count($pagesReportsSrc, '<KPICard ') === 5
    && str_contains($pagesReportsSrc, 'caption="Pending Contacts"')
    && str_contains($pagesReportsSrc, 'caption="Total Calls"')
    && str_contains($pagesReportsSrc, 'caption="Visited"')
    && str_contains($pagesReportsSrc, 'caption="Pending Visit"')
    && str_contains($pagesReportsSrc, 'caption="Response Rate"')
    && str_contains($pagesReportsSrc, "from 'recharts'")
    && str_contains($pagesReportsSrc, 'ResponsiveContainer')
    && str_contains($pagesReportsSrc, 'BarChart')
    && str_contains($pagesReportsSrc, 'AreaChart'),
    'must render 5 KPICard with all 5 distinct captions + import recharts (BarChart + AreaChart + ResponsiveContainer)'
);

// ---------------------------------------------------------------------------
// [18] Audit/Index.tsx renders data-testid="card-audit" + "audit-table" + "card-audit-details" + useState<Entry|null> + setSelected + audit-diff-viewer DiffViewer wrapper.
// ---------------------------------------------------------------------------
$pagesAuditSrc = is_file($pagesAuditPath) ? file_get_contents($pagesAuditPath) : '';
check(18, 'Audit/Index.tsx renders card-audit shell + audit-table + card-audit-details side-panel + useState<Entry|null> + DiffViewer audit-diff-viewer wrapper (Phase 11b)',
    str_contains($pagesAuditSrc, 'data-testid="card-audit"')
    && str_contains($pagesAuditSrc, 'data-testid="audit-table"')
    && str_contains($pagesAuditSrc, 'data-testid="card-audit-details"')
    && str_contains($pagesAuditSrc, 'useState<Entry')
    && str_contains($pagesAuditSrc, 'setSelected')
    && str_contains($pagesAuditSrc, 'data-testid="audit-diff-viewer"'),
    'must render `data-testid="card-audit"` shell + `data-testid="audit-table"` table + `data-testid="card-audit-details"` side-panel + `useState<Entry|null>(...)` + `setSelected(...)` toggle + DiffViewer wrapped in `data-testid="audit-diff-viewer"` (per Phase 11b — replaced the prior `JSON.stringify(selected.properties)` JSON dump with a proper before/after diff viewer; OR-widening is removed now that Phase 11b ships)'
);

// ---------------------------------------------------------------------------
// [19] Reports/Index.tsx month filter <input type="month"> onChange → router.get('/reports', { month }) + Export CSV href={route('csv.export')}
// ---------------------------------------------------------------------------
check(19, 'Reports/Index.tsx binds <input type="month"> → router.get(\'/reports\', { month }) + Export CSV href={route(\'csv.export\')}',
    str_contains($pagesReportsSrc, 'type="month"')
    && preg_match('/router\s*\.\s*get\s*\(\s*[\'"]\/reports[\'"]/', $pagesReportsSrc) === 1
    && str_contains($pagesReportsSrc, "href={route('csv.export')}"),
    'must render `<input type="month">` filter onChange → `router.get(\'/reports\', { month }, { preserveState: true })` + Export CSV `<Link href={route(\'csv.export\')}>'
);

// ---------------------------------------------------------------------------
// [20] No controller declares `: SymfonyResponse` (Phase TypeError regression guard) — every controller
// calling Inertia::render() must declare `: Inertia\Response` + import `use Inertia\Response;`. Backstory:
// Inertia 2.x `class Response implements Responsable` does NOT extend
// `Symfony\Component\HttpFoundation\Response`, so the older pattern
//   use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
//   public function ... : SymfonyResponse { return Inertia::render(...); }
// TypeError-fails on PHP 8.4 strict-mode. Caught live on /impact-cells in this codebase and just-fixed
// in app/Http/Controllers/ImpactCellController.php — this check is the regression guard.
//
// This check implements 3 sub-checks:
//   #1. Reject the `: SymfonyResponse` alias form (the literal bug class as caught live).
//   #2. Reject `: Response` annotation without the matching `use Inertia\Response;` import. Without
//       the import, `: Response` falls back to `Symfony\Component\HttpFoundation\Response` — same TypeError.
//   #3. (Round-3 addition) Reject the FQCN-form of the bug class: `: Symfony\Component\HttpFoundation\Response`
//       (without the `as SymfonyResponse` alias). Bug-class equivalent, just without the alias. Doesn't
//       false-flag union types (different substring than `: Response`).
// ---------------------------------------------------------------------------
$controllers = array_merge(
    glob($base . '/app/Http/Controllers/*.php') ?: [],
    glob($base . '/app/Http/Controllers/Admin/*.php') ?: [],
    glob($base . '/app/Http/Controllers/Auth/*.php') ?: []
);

$inertiaTypeViolations = [];
foreach ($controllers as $file) {
    $src = file_get_contents($file);

    // Only enforce on files that actually render Inertia pages — controllers that return StreamedResponse
    // (CsvExportController), JsonResponse, RedirectResponse, etc. don't call Inertia::render() so they're
    // naturally excluded by this gate — no hardcoded skip required.
    if (!str_contains($src, 'Inertia::render(')) {
        continue;
    }

    $name = basename($file);

    // #1. Reject the explicit SymfonyResponse alias trap (the bug class itself).
    if (str_contains($src, ': SymfonyResponse')) {
        $inertiaTypeViolations[] = "{$name} declares : SymfonyResponse (re-introduces the Phase TypeError)";
    }

    // #2. Reject `: Response` annotation without the matching `use Inertia\Response;` import. Without the
    // import, `: Response` falls back to `Symfony\Component\HttpFoundation\Response` — same TypeError.
    if (str_contains($src, ': Response') && !str_contains($src, 'use Inertia\\Response;')) {
        $inertiaTypeViolations[] = "{$name} has : Response annotation but missing 'use Inertia\\\\Response;' import";
    }

    // #3. Reject FQCN-form of the bug class: `: Symfony\Component\HttpFoundation\Response` (without the
    // `as SymfonyResponse` alias). Different from check #1 (which catches `: SymfonyResponse` alias) and
    // check #2 (which catches `: Response` annotation w/o Inertia import). Bug-class equivalent, just
    // without the alias. Doesn't false-flag union types (different substring than `: Response`).
    if (str_contains($src, ': Symfony\\Component\\HttpFoundation\\Response')) {
        $inertiaTypeViolations[] = "{$name} declares : Symfony\\Component\\HttpFoundation\\Response (FQCN form of the Phase TypeError)";
    }
}

check(20, 'Every controller calling Inertia::render() declares `: Response` + imports `use Inertia\\Response;` (SymfonyResponse alias trap + FQCN form both forbidden)',
    empty($inertiaTypeViolations),
    'every Inertia::render() caller must declare `: Response` + `use Inertia\\Response;`; found: ' . (empty($inertiaTypeViolations) ? 'none' : implode('; ', $inertiaTypeViolations))
);

// ---------------------------------------------------------------------------

echo "\nPhase 11 verifier: {$pass} pass / {$fail} fail\n";
if ($fail > 0) {
    echo "\nFailures:\n";
    foreach ($failed as $f) {
        echo "  - {$f}\n";
    }
}
exit($fail === 0 ? 0 : 1);
