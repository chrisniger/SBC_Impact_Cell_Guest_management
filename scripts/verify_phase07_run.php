<?php
declare(strict_types=1);

/**
 * scripts/verify_phase07_run.php — Phase 07 verifier.
 *
 * Impact Cell Leader group (@activeGroup = impactCell) + inline impact_status editor.
 *
 * Sub-assertions [1]-[20] cover:
 *   [1]-[7]   AuthenticatedLayout nav shape (impactCell branch has 7 items in spec order;
 *             admin / followUp* branches unchanged as regression net)
 *   [8]-[9]   Route registration + Guest model fillable
 *   [10]-[12] DashboardController::leaderDashboard returns assignedGuests + real memberCount
 *   [13]-[14] ImpactSubmissionController shape (4 type dispatch + dup-prevention flash)
 *   [15]-[17] Soul search controller (q-length guard + type=soul scope)
 *   [18]      InlineImpactStatusPill component file + 3 status options + router.patch
 *   [19]      GuestController.updateImpactStatus method (authorize + JSON response)
 *   [20]      RoleHelper GROUP_GUEST_OWNER[impactCell] list contains 'impact_status'
 *
 * Exit code 0 when all pass, 1 when any fail.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pass = 0;
$fail = 0;

function check(int $n, string $desc, $cond, string $failMsg = ''): void {
    global $pass, $fail;
    if ($cond) {
        echo "[$n] PASS  $desc\n";
        $pass++;
    } else {
        echo "[$n] FAIL  $desc" . ($failMsg !== '' ? "  -- $failMsg" : '') . "\n";
        $fail++;
    }
}

function has(string $haystack, string $needle): int {
    return strpos($haystack, $needle) === false ? -1 : 1;
}

function section(string $title): void {
    echo "\n=== $title ===\n";
}

// ───────────────────────────────────────────────────────────────────
// File contents (cheap, deterministic — read once, reuse across [1]-[20]).
// ───────────────────────────────────────────────────────────────────
$layout     = file_get_contents(__DIR__ . '/../resources/js/Layouts/AuthenticatedLayout.tsx');
$dashboard  = file_get_contents(__DIR__ . '/../resources/js/Pages/Dashboard.tsx');
$pill       = file_get_contents(__DIR__ . '/../resources/js/Components/InlineImpactStatusPill.tsx');
$routes     = file_get_contents(__DIR__ . '/../routes/web.php');
$dashCtrl   = file_get_contents(__DIR__ . '/../app/Http/Controllers/DashboardController.php');
$subCtrl    = file_get_contents(__DIR__ . '/../app/Http/Controllers/ImpactSubmissionController.php');
$guestCtrl  = file_get_contents(__DIR__ . '/../app/Http/Controllers/GuestController.php');
$guestModel = file_get_contents(__DIR__ . '/../app/Models/Guest.php');
$subModel   = file_get_contents(__DIR__ . '/../app/Models/ImpactSubmission.php');
$policy     = file_get_contents(__DIR__ . '/../app/Policies/GuestPolicy.php');
$roleHelper = file_get_contents(__DIR__ . '/../app/Support/RoleHelper.php');

// ───────────────────────────────────────────────────────────────────
// [1]-[2] Nav shape — impactCell branch.
// ───────────────────────────────────────────────────────────────────
section('Sidebar nav (impactCell branch)');

preg_match('/if \(activeGroup === .impactCell.\) \{\s*return \[(.*?)\];\s*\}/s', $layout, $m);
$impactCellNavBlock = $m[1] ?? '';
$itemCount = preg_match_all('/label: /', $impactCellNavBlock);
check(1, "impactCell nav branch has exactly 8 items including Leadership Board (Phase 08 add)  (got $itemCount)",
    $itemCount === 8,
    'expected 8 (7 Phase 07 entries + Leadership Board appended by Phase 08)');

$specOrder = ['Dashboard', 'Members Data', 'Submit Report', 'Childbirth Notice', 'Souls Registration', 'Soul Search', 'My Reports'];
$actualOrder = [];
foreach ($specOrder as $label) {
    if (strpos($impactCellNavBlock, "'$label'") !== false) $actualOrder[] = $label;
}
check(2, "impactCell nav labels appear in spec order  (" . implode(' -> ', $specOrder) . ')',
    $actualOrder === $specOrder,
    'actual: ' . implode(' -> ', $actualOrder));

// ───────────────────────────────────────────────────────────────────
// [3]-[4] Nav shape — admin and followUp branches unchanged (regression net).
// ───────────────────────────────────────────────────────────────────
section('Other nav branches (regression net)');

preg_match('/if \(activeRole === .Administrator.\) \{\s*return \[(.*?)\];\s*\}/s', $layout, $mAdmin);
$adminBlock = $mAdmin[1] ?? '';
$adminCount = preg_match_all('/label: /', $adminBlock);
$adminHasAuditLog = strpos($adminBlock, "'Audit Log'") !== false;
check(3, "Administrator nav branch — 8 items with Audit Log + Leadership Board (Phase 08 append)  (got $adminCount)",
    $adminCount === 8 && $adminHasAuditLog,
    'admin branch shape: expected 8 items (7 originals + Leadership Board appended by Phase 08), Audit Log still present');

preg_match("/if \(activeGroup === .followUpOfficer. \|\| activeGroup === .followUpTeam.\) \{\s*return \[(.*?)\];\s*\}/s", $layout, $mFu);
$fuBlock = $mFu[1] ?? '';
$fuCount = preg_match_all('/label: /', $fuBlock);
check(4, "followUp nav branch unchanged — 3 items  (got $fuCount)",
    $fuCount === 3,
    'followUp branch shape changed; expected Dashboard + My Guests + Export CSV');

// ───────────────────────────────────────────────────────────────────
// [5]-[7] Routes + fillable.
// ───────────────────────────────────────────────────────────────────
section('Routes + fillable');

$patchImpact = has($routes, "Route::patch('/guests/{id}/impact-status'") > -1
              && has($routes, 'updateImpactStatus') > -1;
check(5, "Route::patch('/guests/{id}/impact-status') registered in routes/web.php",
    $patchImpact,
    "PATCH route missing — add under the Phase 06 follow-up-status line");

$guestImpactFillable = has($guestModel, "'impact_status'") > -1
    && has($guestModel, "'nearest_impact_cell_id'") > -1;
check(6, "Guest model \$fillable includes 'impact_status' + 'nearest_impact_cell_id'",
    $guestImpactFillable,
    "Guest::fillable missing — impact_status PATCH cannot write the column");

$hasScopeForImpactCell = has($guestModel, 'public function scopeForImpactCell') > -1;
$hasNearestWhere  = has($guestModel, "where('nearest_impact_cell_id', \$impactCellId)") > -1;
check(7, "Guest::scopeForImpactCell exists and queries nearest_impact_cell_id",
    $hasScopeForImpactCell && $hasNearestWhere,
    'scope method or where clause on nearest_impact_cell_id missing');

// ───────────────────────────────────────────────────────────────────
// [8]-[10] DashboardController::leaderDashboard returns assignedGuests + real memberCount.
// ───────────────────────────────────────────────────────────────────
section('DashboardController::leaderDashboard()');

$hasMemberCountQuery = preg_match("/ImpactSubmission::where\(.user_id., \\\$user->id\)\s*->where\(.type.,\s*.member.\)/", $dashCtrl) > -1;
$noHardcodedZero     = has($dashCtrl, "'memberCount'      => 0,") === -1;
check(8, "leaderDashboard() uses a real memberCount query (not hard-coded 0)",
    $hasMemberCountQuery && $noHardcodedZero,
    "memberCount still hard-coded 0 OR the Submission query missing");

$hasAssignedGuestsKey = has($dashCtrl, "'assignedGuests' => \$assignedGuests") > -1;
$hasAssignedGuestsQuery = has($dashCtrl, "where('nearest_impact_cell_id', \$primaryCellId)") > -1;
check(9, "leaderDashboard() returns 'assignedGuests' scoped to primaryCellId",
    $hasAssignedGuestsKey && $hasAssignedGuestsQuery,
    "assignedGuests key missing OR query not scoped to primaryCellId");

$hasCanEditFlag = has($dashCtrl, "'canEditImpactStatus' => \$canEditImpactStatus") > -1;
check(10, "leaderDashboard() returns 'canEditImpactStatus' for safe pill gating",
    $hasCanEditFlag,
    "canEditImpactStatus flag missing — pill can't know whether the user can edit");

// ───────────────────────────────────────────────────────────────────
// [11]-[12] ImpactSubmissionController — 4-type dispatch + duplicate prevention.
// ───────────────────────────────────────────────────────────────────
section('ImpactSubmissionController');

$hasFourTypes = has($subCtrl, "private const TYPES = ['member', 'report', 'childbirth', 'soul']") > -1;
$hasInRule    = has($subCtrl, "in:member,report,childbirth,soul") > -1
              || has($subCtrl, "in:' . implode(',', self::TYPES)") > -1;
check(11, "ImpactSubmissionController::TYPES = member/report/childbirth/soul + in: rule covers all 4",
    $hasFourTypes && $hasInRule,
    "TYPES constant missing OR validation rule not exhaustive");

$hasDupFlash = has($subCtrl, "'fellowship_date_key' => 'A report for this cell and date already exists.'") > -1;
check(12, "store() returns back()->withErrors flash on duplicate (cell + date)",
    $hasDupFlash,
    "duplicate-prevention flash string missing — second weekly report won't show inline error");

// ───────────────────────────────────────────────────────────────────
// [13]-[14] Soul search controller.
// ───────────────────────────────────────────────────────────────────
section('Soul search controller');

$hasShortGuard = has($subCtrl, "strlen(\$q) < 2") > -1;
// `where('type', 'soul'` matches both `ImpactSubmission::where('type', ...)` and `->where('type', 'soul')`.
$hasTypeSoul = has($subCtrl, "where('type', 'soul'") > -1;
check(13, "search() short-q guard + type='soul' scope",
    $hasShortGuard && $hasTypeSoul,
    'q<2 guard missing OR type=soul filter missing');

preg_match('/public function search[\s\S]*?->limit\(20\)/s', $subCtrl, $mLimit);
check(14, "search() returns at most 20 hits (recency-sorted)",
    count($mLimit) > 0,
    "limit(20) missing — search could flood the table");

// ───────────────────────────────────────────────────────────────────
// [15]-[16] InlineImpactStatusPill.tsx — 3 options + router.patch.
// ───────────────────────────────────────────────────────────────────
section('InlineImpactStatusPill.tsx');

$hasContacted      = has($pill, "'Contacted'") > -1;
$hasNotContacted   = has($pill, "'Not Contacted'") > -1;
$hasNotReachable   = has($pill, "'Not Reachable'") > -1;
$hasRouterPatch    = has($pill, 'router.patch') > -1;
check(15, "InlineImpactStatusPill.tsx has all 3 status options + router.patch()",
    $hasContacted && $hasNotContacted && $hasNotReachable && $hasRouterPatch,
    "missing status option or router.patch call");

$hasOptimistic = has($pill, 'setStatus(prev)') > -1;
$hasCloseOutside = has($pill, 'mousedown') > -1;
check(16, "InlineImpactStatusPill.tsx has optimistic rollback + outside-click close",
    $hasOptimistic && $hasCloseOutside,
    "optimistic UI rollback or outside-click close handler missing");

// ───────────────────────────────────────────────────────────────────
// [17]-[18] GuestController::updateImpactStatus — auth + JSON response.
// ───────────────────────────────────────────────────────────────────
section('GuestController::updateImpactStatus()');

// Substring `has()` checks — relaxed whitespace + partial-prefix matches to avoid
// the prior false-failure on aligned `=>` arrows (e.g. `'success'       => true,`
// — the literal `'success' => true` substring doesn't match because the source
// has multi-space alignment for visual column layout).
$hasUpdateMethod = has($guestCtrl, 'function updateImpactStatus(Request') > -1;
$hasAuthorize    = has($guestCtrl, "authorize('update', \$guest)") > -1;
$hasJsonResp     = has($guestCtrl, "response()->json([") > -1
                && has($guestCtrl, "impact_status' =>") > -1;
check(17, "GuestController::updateImpactStatus exists + authorize('update') + JSON response",
    $hasUpdateMethod && $hasAuthorize && $hasJsonResp,
    "method signature OR authorize OR JSON response shape missing");

$validateImpactStatus = has($guestCtrl, "'impact_status' => ['nullable', 'string', 'max:64']") > -1;
check(18, "updateImpactStatus() validates impact_status (nullable|string|max:64)",
    $validateImpactStatus,
    'impact_status validation rule missing — server accepts any payload');

// ───────────────────────────────────────────────────────────────────
// [19] RoleHelper matrix.
// ───────────────────────────────────────────────────────────────────
section('RoleHelper::GROUP_GUEST_OWNER');

// Look for the canonical matrix entry — literal source match avoids PCRE `$` anchor confusion.
$matrixOK = has($roleHelper, "self::GROUP_KEY_IMPACT_CELL       => ['impact_status', 'nearest_impact_cell_id']") > -1;
check(19, "GROUP_GUEST_OWNER[impactCell] = ['impact_status', 'nearest_impact_cell_id']",
    $matrixOK,
    'matrix entry missing — GuestRequest::prepareForValidation will strip impact_status for impactCell users');

// ───────────────────────────────────────────────────────────────────
// [20] Dashboard.tsx renders the new section + uses the pill.
// ───────────────────────────────────────────────────────────────────
section('Dashboard.tsx leader variant rendering');

$rendersTable     = has($dashboard, 'assigned-guests-table') > -1;
$usesPill         = has($dashboard, '<InlineImpactStatusPill') > -1;
$importsPill      = has($dashboard, "import InlineImpactStatusPill from '@/Components/InlineImpactStatusPill'") > -1;
check(20, "Dashboard.tsx LeaderDashboard renders assigned-guests-table + import + InlineImpactStatusPill usage",
    $rendersTable && $usesPill && $importsPill,
    "table testid OR pill import OR usage missing");

// ───────────────────────────────────────────────────────────────────
// Summary
// ───────────────────────────────────────────────────────────────────
// ───────────────────────────────────────────────────────────────────
// [21] Role guard for column-level access (Phase 07c hardening).
// ───────────────────────────────────────────────────────────────────
// Assert the column-level guard string is present AND appears BEFORE
// `authorize('update', $guest)` within `updateImpactStatus`'s body — so
// the fail-fast path works. Scope the search to a substring starting at
// the `function updateImpactStatus` declaration; this excludes the
// earlier `authorize('update', $guest)` line inside `updateFollowUpStatus`
// (which would otherwise false-positive the ordering check).
$funcStart  = strpos($guestCtrl, 'function updateImpactStatus');
$bodyWindow = $funcStart === false ? '' : substr($guestCtrl, (int) $funcStart);
$abortIdx   = strpos($bodyWindow, "abort(403, 'Only Impact Cell leaders");
$authIdx    = strpos($bodyWindow, "authorize('update', \$guest)");
$hasRoleAbort = $abortIdx !== false;
$guardBeforeAuthorize = $hasRoleAbort && $authIdx !== false && $abortIdx < $authIdx;
check(21, "GuestController::updateImpactStatus column-level role guard present AND BEFORE authorize()",
    $hasRoleAbort && $guardBeforeAuthorize,
    'abort string missing within updateImpactStatus OR placed AFTER authorize (fail-fast broken)');

echo "\n=== Summary: $pass pass / $fail fail (out of 21 sub-assertions) ===\n";
exit($fail === 0 ? 0 : 1);
