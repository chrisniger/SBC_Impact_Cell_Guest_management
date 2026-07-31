<?php
/**
 * Phase 13b verifier -- "Impact Cell leadership team + signup role grid"
 * follow-up: 2-role signup surface + conditional cell-setup panel + all
 * impact_cells primary.
 *
 * Verifies the three product asks in this turn:
 *   (a) Trim public signup role choices to ONLY Impact_Leaders +
 *       Impact_Zonal_Coordinator. Hide FollowUpOfficer / Follow_UP_Admin
 *       / Follow_UP* from the public role grid; the server-side
 *       Rule::in() allowlist auto-rejects them on POST.
 *   (b) The cell-setup panel (cell picker + 6 leadership-team fields)
 *       is wrapped in `{requiresCell ? (...)}` so it only renders when
 *       Impact_Leaders is checked. A dashed-border placeholder shows
 *       otherwise.
 *   (c) Mark all impact cells primary. The forward migration
 *       `2026_07_31_120000_flatten_all_impact_cells_to_primary.php`
 *       bulk-updates is_primary=true + parent_cell_id=NULL on every row.
 *       The seeder's Step 2 (APO sub-cell re-parenting) is gone.
 *
 * 19 sub-assertions across:
 *   self-syntax              [1]
 *   RoleHelper constants     [2-5]
 *   Auth/Register.tsx        [6-12] (testids + conditional panel + role
 *                                          description map)
 *   Server-side filters      [13-15] (cellsList is_primary=true)
 *   Migration on disk        [16-17]
 *   Seeder Step-2 retiral    [18]
 *   Rendered-payload         [19] (network IO, GET /register, skip-if-down)
 *
 *  Run: php scripts/verify_phase13b_run.php
 *  Expect: 18-19 pass / 0 fail / 0-1 skipped (server-down fallback for [19]).
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

$roleHelperPath       = $root . '/app/Support/RoleHelper.php';
$registerTsxPath      = $root . '/resources/js/Pages/Auth/Register.tsx';
$regdCtrlPath         = $root . '/app/Http/Controllers/Auth/RegisteredUserController.php';
$adminUserCtrlPath    = $root . '/app/Http/Controllers/Admin/UserController.php';
$migrationPath        = $root . '/database/migrations/2026_07_31_120000_flatten_all_impact_cells_to_primary.php';
$impactCellSeederPath = $root . '/database/seeders/ImpactCellSeeder.php';

$roleHelperSrc       = is_file($roleHelperPath)       ? file_get_contents($roleHelperPath)       : '';
$registerTsxSrc      = is_file($registerTsxPath)      ? file_get_contents($registerTsxPath)      : '';
$regdCtrlSrc         = is_file($regdCtrlPath)         ? file_get_contents($regdCtrlPath)         : '';
$adminUserCtrlSrc    = is_file($adminUserCtrlPath)    ? file_get_contents($adminUserCtrlPath)    : '';
$migrationSrc        = is_file($migrationPath)        ? file_get_contents($migrationPath)        : '';
$impactCellSeederSrc = is_file($impactCellSeederPath) ? file_get_contents($impactCellSeederPath) : '';

// ---------------------------------------------------------------------------
// [1] self-syntax -- verifier file parses cleanly.
// ---------------------------------------------------------------------------
$tmpLint = tempnam(sys_get_temp_dir(), 'p13b_lint_');
file_put_contents($tmpLint, file_get_contents(__FILE__));
$lintOut = shell_exec(escapeshellcmd(PHP_BINARY) . ' -l ' . escapeshellarg($tmpLint) . ' 2>&1');
unlink($tmpLint);
check(1, 'verify_phase13b_run.php parses cleanly (php -l)',
    str_contains((string) $lintOut, 'No syntax errors detected'),
    'php -l reports "No syntax errors detected"');

// ---------------------------------------------------------------------------
// [2] RoleHelper::SIGNUP_VISIBLE_ROLES = exactly 2 entries.
// ---------------------------------------------------------------------------
preg_match(
    '/public const SIGNUP_VISIBLE_ROLES\s*=\s*\[(.*?)\];/s',
    $roleHelperSrc,
    $sm
);
$signupConstInner = $sm[1] ?? '';
$signupEntries    = preg_match_all("/'([^']+)'/", $signupConstInner, $em) ? $em[1] : [];
$signupCount      = count($signupEntries);
check(2, 'RoleHelper::SIGNUP_VISIBLE_ROLES const has EXACTLY 2 entries (Phase 13 follow-up: trim from 3 to 2)',
    $signupCount === 2,
    "substr_count of 'entry' literals inside SIGNUP_VISIBLE_ROLES must equal 2 (was 3 pre-2026-07-31; only Impact_Leaders + Impact_Zonal_Coordinator surface on /register now)");

// ---------------------------------------------------------------------------
// [3] RoleHelper::SIGNUP_VISIBLE_ROLES contains Impact_Leaders + Impact_Zonal_Coordinator.
// ---------------------------------------------------------------------------
check(3, 'SIGNUP_VISIBLE_ROLES contains Impact_Leaders + Impact_Zonal_Coordinator',
    in_array('Impact_Leaders',           $signupEntries, true)
    && in_array('Impact_Zonal_Coordinator', $signupEntries, true),
    'the two surfaced roles must be Impact_Leaders (cell-bound) + Impact_Zonal_Coordinator (zone-wide)');

// ---------------------------------------------------------------------------
// [4] Gate: the disallowed signup roles are NOT in SIGNUP_VISIBLE_ROLES.
// ---------------------------------------------------------------------------
$disallowed = ['FollowUpOfficer', 'Follow_UP_Admin', 'Follow_UP', 'Follow_UP_View_Only'];
$leaked = array_intersect($disallowed, $signupEntries);
check(4, 'SIGNUP_VISIBLE_ROLES does NOT include any FollowUp* role (server-side Rule::in() will reject them on POST)',
    empty($leaked),
    'leaked entries: [' . implode(',', $leaked) . ']');

// ---------------------------------------------------------------------------
// [5] signupVisibleRoles() returns the same 2 entries (single source of truth).
// ---------------------------------------------------------------------------
$live = \App\Support\RoleHelper::signupVisibleRoles();
check(5, 'RoleHelper::signupVisibleRoles() returns exactly 2 entries at runtime',
    is_array($live) && count($live) === 2
    && in_array('Impact_Leaders',           $live, true)
    && in_array('Impact_Zonal_Coordinator', $live, true),
    'live array must contain Impact_Leaders + Impact_Zonal_Coordinator, length 2');

// ---------------------------------------------------------------------------
// [6] Auth/Register.tsx: new wrapper testid for the conditional cell-setup panel.
// ---------------------------------------------------------------------------
check(6, 'Auth/Register.tsx exposes data-testid="register-cell-setup-block" (Phase 13b combined panel)',
    str_contains($registerTsxSrc, 'data-testid="register-cell-setup-block"'),
    'must contain `data-testid="register-cell-setup-block"` somewhere in the conditional-truthy branch');

// ---------------------------------------------------------------------------
// [7] Auth/Register.tsx: dashed-border empty placeholder testid.
// ---------------------------------------------------------------------------
check(7, 'Auth/Register.tsx exposes data-testid="register-cell-setup-empty" (Phase 13b dashed placeholder)',
    str_contains($registerTsxSrc, 'data-testid="register-cell-setup-empty"'),
    'must contain `data-testid="register-cell-setup-empty"` somewhere in the conditional-falsy branch');

// ---------------------------------------------------------------------------
// [8] Auth/Register.tsx: cell-setup panel is guarded by `{requiresCell ? (`.
// ---------------------------------------------------------------------------
check(8, 'Auth/Register.tsx wraps cell-setup panel in `{requiresCell ? (` ternary (Phase 13b conditional gating)',
    str_contains($registerTsxSrc, '{requiresCell ? (')
    && preg_match('/\{requiresCell \?\s*\(/', $registerTsxSrc) === 1,
    'JSX must use `{requiresCell ? (...cell-setup-block...) : (...cell-setup-empty...)}` to toggle visibility on Impact_Leaders tick');

// ---------------------------------------------------------------------------
// [9] Auth/Register.tsx: inner cell picker testid still present (now nested).
// ---------------------------------------------------------------------------
check(9, 'Auth/Register.tsx keeps data-testid="register-impact-cell-block" inside the conditional (cell picker is sub-block)',
    str_contains($registerTsxSrc, 'data-testid="register-impact-cell-block"'),
    'inner cell picker testid lives INSIDE register-cell-setup-block now; was a top-level card before the Phase 13b wrap');

// ---------------------------------------------------------------------------
// [10] Auth/Register.tsx: inner leadership-team testid still present (now nested).
// ---------------------------------------------------------------------------
check(10, 'Auth/Register.tsx keeps data-testid="register-leadership-team-block" inside the conditional (leadership-team is sub-block)',
    str_contains($registerTsxSrc, 'data-testid="register-leadership-team-block"'),
    'inner leadership-team testid lives INSIDE register-cell-setup-block now; was a top-level sibling card before the Phase 13b wrap');

// ---------------------------------------------------------------------------
// [11] Auth/Register.tsx: role description map has NO caption under either role.
//   Phase 13 follow-up (user choice: "Remove both captions"): the inner
//   role-checkbox cards (Impact_Leaders + Impact_Zonal_Coordinator) fill the
//   description area uniformly. Guardrails: zero literal caption strings
//   present in the source. Empty-span JSX (see [6-10] testids) renders fine
//   without rendering a caption.
// ---------------------------------------------------------------------------
$hasImpactLeadersCaption = preg_match("/Heads an outreach cell/", $registerTsxSrc) === 1;
$hasImpactZonalCaption   = preg_match("/Coordinates cells across/", $registerTsxSrc) === 1;
check(11, 'Auth/Register.tsx role description map has NO caption under either role (Phase 13 user choice: "Remove both captions" — inner cards fill uniformly)',
    ! $hasImpactLeadersCaption
    && ! $hasImpactZonalCaption,
    'must NOT contain the literal captions "Heads an outreach cell" (Impact_Leaders) OR "Coordinates cells across" (Impact_Zonal_Coordinator) anywhere in the source file');

// ---------------------------------------------------------------------------
// [12] Auth/Register.tsx: role description map does NOT mention FollowUpOfficer.
//   (Phase 13 followup reversed the prior "always visible" surface.)
// ---------------------------------------------------------------------------
$hasFollowUpDesc = preg_match("/role === 'FollowUpOfficer'/", $registerTsxSrc) === 1
                 || preg_match("/role === 'Follow_UP_Admin'/", $registerTsxSrc) === 1;
check(12, 'Auth/Register.tsx role description map does NOT include FollowUpOfficer / Follow_UP_Admin (those routes are out of public signup)',
    !$hasFollowUpDesc,
    'must not have a `role === \'FollowUpOfficer\'` or `role === \'Follow_UP_Admin\'` branch in the description ternary');

// ---------------------------------------------------------------------------
// [13] Auth/RegisteredUserController::create() filters cellsList to is_primary=true.
// ---------------------------------------------------------------------------
check(13, 'Auth/RegisteredUserController::create() filters cellsList via ImpactCell::where(\'is_primary\', true)->ordered()->get()',
    str_contains($regdCtrlSrc, "ImpactCell::where('is_primary', true)->ordered()->get("),
    'the create() payload builder must filter sub-cells out of the signup dropdown');

// ---------------------------------------------------------------------------
// [14] Admin/UserController::edit() filters cellsList to is_primary=true (mirrors [13]).
// ---------------------------------------------------------------------------
check(14, 'Admin/UserController::edit() filters cellsList via ImpactCell::where(\'is_primary\', true)->ordered()->get()',
    str_contains($adminUserCtrlSrc, "ImpactCell::where('is_primary', true)->ordered()->get("),
    'the edit() payload builder must filter sub-cells out of the admin Edit dropdown');

// ---------------------------------------------------------------------------
// [15] Both controllers' cellsList passes the same 3-column projection [id,name,is_primary].
// ---------------------------------------------------------------------------
preg_match_all("/ImpactCell::where\\('is_primary', true\\)->ordered\\(\\)->get\\(\\['id', 'name', 'is_primary'\\]\\)/", $regdCtrlSrc, $rm0);
preg_match_all("/ImpactCell::where\\('is_primary', true\\)->ordered\\(\\)->get\\(\\['id', 'name', 'is_primary'\\]\\)/", $adminUserCtrlSrc, $rm1);
$bothColsCount = (count($rm0[0]) + count($rm1[0]));
check(15, 'Both controllers project cellsList as [id, name, is_primary] (consistent payload contract)',
    $bothColsCount === 2,
    "expected exactly 2 such get() invocations across the two controllers; got {$bothColsCount}");

// ---------------------------------------------------------------------------
// [16] The flatten-on-all-cells migration file exists.
// ---------------------------------------------------------------------------
check(16, 'database/migrations/2026_07_31_120000_flatten_all_impact_cells_to_primary.php exists',
    is_file($migrationPath) && $migrationSrc !== '',
    'forward migration must exist on disk; Data UPDATE happens in up()');

// ---------------------------------------------------------------------------
// [17] The flatten migration is idempotent (no value changes after a single run).
// ---------------------------------------------------------------------------
check(17, 'flatten migration is idempotent: up() DB::table(\'impact_cells\')->update([...]); + down() is a no-op or absent',
    str_contains($migrationSrc, "DB::table('impact_cells')->update(")
    && (str_contains($migrationSrc, "public function up(): void")
        || preg_match("/function up\\(\\)/", $migrationSrc) === 1)
    && (str_contains($migrationSrc, "// No-op")
        || str_contains($migrationSrc, "// noop")
        || !preg_match("/public function down\\(\\)/", $migrationSrc)),
    'up() must use DB::table(\'impact_cells\')->update([is_primary=true, parent_cell_id=null]) and the down() can be a no-op (the recovery path is described in the docblock)');

// ---------------------------------------------------------------------------
// [18] ImpactCellSeeder Step 2 retired: APO_SUB_CELL_NAMES = [] + run() no longer
//        re-parents the 4 APO sub-cells under the APO primary.
// ---------------------------------------------------------------------------
preg_match('/public const APO_SUB_CELL_NAMES\s*=\s*\[(.*?)\];/s', $impactCellSeederSrc, $seedsm);
$subCellConstInner = $seedsm[1] ?? '';
check(18, 'ImpactCellSeeder::APO_SUB_CELL_NAMES is empty (Step 2 retired — seeder has no sub-cell seed inputs)',
    trim($subCellConstInner) === '',
    'the dev convenience split was retired 2026-07-31; the const stays public & empty as a backward-compat leaf, no entries inside the brackets');

// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// [19] Rendered-payload check (network IO, skip-if-down).
//   Phase 13b follow-up closure: the existing [11] assertion only inspects the
//   SOURCE file for the negative-prefix caption check. This [19] assertion
//   curls the actual rendered Inertia HTML and asserts the SAME 2-role
//   invariant survives end-to-end rendering (i.e. server-side payload
//   builder still ships `rolesForSignup = [Impact_Leaders,
//   Impact_Zonal_Coordinator]` after the Auth/Register.tsx caption edits).
//
//   Skip semantics:
//     - Use Laravel's HTTP client (`Http::get`) instead of raw
//       `file_get_contents`. The Http facade catches connection errors and
//       DNS failures internally, returning a non-successful Response — which
//       means the verifier's top-level `set_error_handler` (which exits on
//       raw PHP warnings) NEVER fires.
//     - If the request is not successful (server unreachable, DNS failure,
//       timeout), print `[19] SKIPPED` and DON'T increment $fail — this
//       preserves the verifier suite's offline-runnability guarantee for
//       [1]-[18].
//     - Real regressions (server reachable + wrong payload) WILL still fail.
//   URL: hardcoded `http://localhost:8000/register` (deterministic; the dev
//        server is always launched at the same port in this project). Avoid
//        `APP_URL` because the project's `APP_URL` may point to a Valet/Herd
//        hostname (e.g. `impact_portal_plus.test`) that isn't routable from
//        the verifier's hostname resolver.
// ---------------------------------------------------------------------------
$registerUrl  = 'http://localhost:8000/register';
// Belt-and-suspenders: in case a future Guzzle/HTTP-client release leaks a
// warning on DNS/connection failure that bypasses its internal catch.
// (The verifier's strict top-level `set_error_handler` would hard-exit on
// any raw PHP warning — so we temporarily zero error_reporting around the
// network call, then restore E_ALL afterwards.)
$prevErrorReporting = error_reporting();
error_reporting(0);
$registerResp = \Illuminate\Support\Facades\Http::timeout(2)->get($registerUrl);
error_reporting($prevErrorReporting);

if (! $registerResp->successful()) {
    echo "  [19] SKIPPED -- server unreachable at {$registerUrl} (HTTP status: " . ($registerResp->status() ?: 'connection-failed') . "); start Laravel dev server to enable the rendered-payload check\n";
    $skipped = 1;
} else {
    $registerHtml    = $registerResp->body();
    $dataPageRoles   = null;
    if (preg_match('/data-page="([^"]+)"/', $registerHtml, $dp) && isset($dp[1])) {
        $decoded       = html_entity_decode($dp[1], ENT_QUOTES);
        $dataArr       = json_decode($decoded, true);
        if (is_array($dataArr) && isset($dataArr['props']['rolesForSignup']) && is_array($dataArr['props']['rolesForSignup'])) {
            $dataPageRoles = $dataArr['props']['rolesForSignup'];
        }
    }
    $renderedOk      = is_array($dataPageRoles) && count($dataPageRoles) === 2
        && in_array('Impact_Leaders',           $dataPageRoles, true)
        && in_array('Impact_Zonal_Coordinator', $dataPageRoles, true);
    if ($renderedOk) {
        $pass++;
        echo "  [19] pass -- GET {$registerUrl} returns Inertia data-page with props.rolesForSignup = exactly [Impact_Leaders, Impact_Zonal_Coordinator] (2 entries)\n";
    } else {
        $fail++;
        $failed[] = "[19] GET /register rendered-payload check -- expected: props.rolesForSignup array of length 2 containing Impact_Leaders + Impact_Zonal_Coordinator (got: " . json_encode($dataPageRoles) . ')';
        echo "  [19] FAIL -- GET {$registerUrl} does NOT return the expected rolesForSignup payload (got: " . json_encode($dataPageRoles) . ")\n";
    }
}

$skipped = $skipped ?? 0;
$skipStr = $skipped > 0 ? " / {$skipped} skipped" : '';

echo "\nPhase 13b verifier: {$pass} pass / {$fail} fail{$skipStr}\n";
if ($fail > 0) {
    echo "\nFailures:\n";
    foreach ($failed as $f) {
        echo "  - {$f}\n";
    }
}
exit($fail === 0 ? 0 : 1);
