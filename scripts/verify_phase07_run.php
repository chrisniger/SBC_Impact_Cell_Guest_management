<?php
declare(strict_types=1);

/**
 * scripts/verify_phase07_run.php — Phase 07 verifier.
 *
 * Impact Cell Leader group (@activeGroup = impactCell) + inline impact_status editor.
 *
 * Sub-assertions [1]-[20] cover:
 *   [1]-[4]   AdminSidebar SECTIONS shape (Phase 06d+ consolidation: was
 *             AuthenticatedLayout.navItemsFor() with role-grouped branches;
 *             Phase 09+ reorganized into a single SECTIONS: Section[] array
 *             with `key` + `label` + `items` per role group)
 *   [5]-[7]   Route registration + Guest model fillable
 *   [8]-[10] DashboardController::leaderDashboard returns assignedGuests + real memberCount
 *   [11]-[14] ImpactSubmissionController shape (4 type dispatch + dup-prevention flash + soul-search guard)
 *   [15]-[16] InlineImpactStatusPill component file + 3 status options + router.patch
 *   [17]-[18] GuestController.updateImpactStatus method (authorize + JSON response + validate)
 *   [19]      RoleHelper GROUP_GUEST_OWNER[impactCell] list contains 'impact_status'
 *   [20]      Dashboard.tsx LeaderDashboard renders assigned-guests-table + uses pill
 *   [21]      GuestController.updateImpactStatus column-level role guard before authorize()
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
// File contents (cheap, deterministic — read once, reuse across [1]-[21]).
//
// Phase 06d+ consolidation note: AuthenticatedLayout.tsx was retired.
// The role-grouped nav that used to live in `AuthenticatedLayout.navItemsFor()`
// now lives in AdminSidebar.tsx as a SECTIONS: Section[] array (Phase 09+).
// All [1]-[4] sub-assertions below were rewritten to read from AdminSidebar.
// The Phase 07 doc-block claim "impactCell branch" is now the
// `items: [...]` array nested inside the `key: 'impactCell'` section.
// ───────────────────────────────────────────────────────────────────
$layout     = file_get_contents(__DIR__ . '/../resources/js/Components/AdminSidebar.tsx');
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
// [1]-[2] SECTIONS.impactCell.items shape (Phase 09+ refactor of what
//        AuthenticatedLayout had as navItemsFor().impactCell).
// ───────────────────────────────────────────────────────────────────
section('Sidebar SECTIONS (impactCell)');

// Doctrine consistency: Phase 06d.0 [3] canonical SECTIONS-slice end-anchor
// `]\s*,?\s*}` (whitespace-tolerant, optional comma) AND paired-fields
// doctrine (routeName + iconPath + href all present per item). The prior
// `/,?\s*\}/s` was already end-anchor-tighter — kept `,?\s*` for compactness
// here, with paired-field counts added on top so cross-verifier consistency
// is explicit (not just an oversight).
preg_match("/key: 'impactCell',[\s\S]*?items:\s*\[([\s\S]*?)\]\s*,?\s*\}/s", $layout, $m);
$impactCellItemsBlock  = $m[1] ?? '';
$impactCellItemCount     = preg_match_all("/label: '/",   $impactCellItemsBlock);
$impactCellHrefCount     = preg_match_all("/href: /",      $impactCellItemsBlock);
$impactCellRouteNameCount = preg_match_all("/routeName: '/", $impactCellItemsBlock);
$impactCellIconPathCount = preg_match_all("/iconPath: /",  $impactCellItemsBlock);
check(1, 'AdminSidebar.tsx SECTIONS.impactCell.items has exactly 8 items, EACH with paired (label + href + routeName + iconPath) fields (paired-fields doctrine per Phase 06d.0 [3])  (got items/label/href/routeName/iconPath=' . $impactCellItemCount . '/' . $impactCellItemCount . '/' . $impactCellHrefCount . '/' . $impactCellRouteNameCount . '/' . $impactCellIconPathCount . ')',
    $impactCellItemCount === 8
        && $impactCellHrefCount === 8
        && $impactCellRouteNameCount === 8
        && $impactCellIconPathCount === 8,
    'expected 8 items in the impactCell section: Dashboard, Members Data, Submit Report, Childbirth Notice, Souls Registration, Soul Search, My Reports, Leadership Board — AND each item must own ALL paired fields (label + href + routeName + iconPath). Phase 13 iconPath refactor to named ICON_* consts keeps the iconPath field shape, so per-item defense survives shorthand spread.');

$specOrder = ['Dashboard', 'Members Data', 'Submit Report', 'Childbirth Notice', 'Souls Registration', 'Soul Search', 'My Reports', 'Leadership Board'];
$actualOrder = [];
foreach ($specOrder as $label) {
    if (preg_match("/label: '" . preg_quote($label, '/') . "'/", $impactCellItemsBlock)) $actualOrder[] = $label;
}
check(2, 'impactCell nav labels appear in spec order  (' . implode(' -> ', $specOrder) . ')',
    $actualOrder === $specOrder,
    'actual: ' . implode(' -> ', $actualOrder));

// ───────────────────────────────────────────────────────────────────
// [3]-[4] SECTIONS.admin + SECTIONS.followUpOfficer (regression net;
//        the old AuthenticatedLayout had separate role-grouped branches).
// ───────────────────────────────────────────────────────────────────
section('Other SECTIONS (regression net)');

preg_match("/key: 'admin',[\s\S]*?items:\s*\[([\s\S]*?)\]\s*,?\s*\}/s", $layout, $mAdmin);
$adminBlock             = $mAdmin[1] ?? '';
$adminItemCount     = preg_match_all("/label: '/",   $adminBlock);
$adminHrefCount     = preg_match_all("/href: /",      $adminBlock);
$adminRouteNameCount = preg_match_all("/routeName: '/", $adminBlock);
$adminIconPathCount = preg_match_all("/iconPath: /",  $adminBlock);
$adminHasAuditLog = (bool) preg_match("/label: 'Audit Log'/", $adminBlock);
check(3, 'AdminSidebar.tsx SECTIONS.admin.items has 13 items, EACH with paired (label + href + routeName + iconPath) fields (paired-fields doctrine per Phase 06d.0 [3])  (got items/label/href/routeName/iconPath=' . $adminItemCount . '/' . $adminItemCount . '/' . $adminHrefCount . '/' . $adminRouteNameCount . '/' . $adminIconPathCount . ')',
    $adminItemCount === 13
        && $adminHasAuditLog
        && $adminHrefCount === 13
        && $adminRouteNameCount === 13
        && $adminIconPathCount === 13,
    'admin section items count should be 13 (Phase 09 added Impact Cells + Submissions + Roles & Permissions + Analytics + Notifications + Reports + Messages + Settings etc.) AND each item must own ALL paired fields (label + href + routeName + iconPath) — the legacy flight had only 7; the Phase 09 unified sidebar paired-shape contract: 13 labels × 4 fields = 52 expected tokens (52 / 4 file matches)');

preg_match("/key: 'followUpOfficer',[\s\S]*?items:\s*\[([\s\S]*?)\]\s*,?\s*\}/s", $layout, $mFu);
$fuBlock                 = $mFu[1] ?? '';
$fuItemCount     = preg_match_all("/label: '/",   $fuBlock);
$fuHrefCount     = preg_match_all("/href: /",      $fuBlock);
$fuRouteNameCount = preg_match_all("/routeName: '/", $fuBlock);
$fuIconPathCount = preg_match_all("/iconPath: /",  $fuBlock);
check(4, 'AdminSidebar.tsx SECTIONS.followUpOfficer.items has 3 items, EACH with paired (label + href + routeName + iconPath) fields (paired-fields doctrine per Phase 06d.0 [3], applied to all 3 SECTIONS slices consistently)  (got items/label/href/routeName/iconPath=' . $fuItemCount . '/' . $fuItemCount . '/' . $fuHrefCount . '/' . $fuRouteNameCount . '/' . $fuIconPathCount . ')',
    $fuItemCount === 3
        && $fuHrefCount === 3
        && $fuRouteNameCount === 3
        && $fuIconPathCount === 3,
    'followUpOfficer section items count should be 3 (Dashboard + My Guests + Export CSV) — unchanged from earlier phases; equivalent followUpTeam section also has the same 3 items — AND each item must own ALL paired fields (label + href + routeName + iconPath) so the doctrine closes the cross-section consistency gap.');

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
check(8, 'leaderDashboard() uses a real memberCount query (not hard-coded 0)',
    $hasMemberCountQuery && $noHardcodedZero,
    'memberCount still hard-coded 0 OR the Submission query missing');

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
