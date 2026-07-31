<?php
/**
 * scripts/verify_phase08_run.php
 *
 * Phase 08 verifier — Leadership Board UI (ship).
 *
 * Asserts (19):
 *   [1]  LeadershipBoardController exists
 *   [2]  LeadershipBoardController has index()
 *   [3]  LeadershipBoardController has show($cellId)
 *   [4]  LeadershipBoardController has buildBoardData()
 *   [5]  LeadershipBoardController::index renders Leadership/Index
 *   [6]  LeadershipBoardController::index passes boards array to view
 *   [7]  LeadershipBoardController::index envelope includes generatedAt+fromCache+cacheKey
 *   [8]  LeadershipBoardController::index has role gate (abort/authorize/gate)
 *   [9]  LeadershipBoardController tile data includes leaderFullName + leaderPhone
 *   [10] LeadershipBoardController::show exists + does NOT pass raw impact_leaders data
 *   [11] LeadershipBoard.tsx declares initialData?: BoardData prop
 *   [12] LeadershipBoard.tsx skips fetch when initialData provided
 *   [13] LeadershipBoard.tsx falls back to fetch when initialData is null
 *   [14] resources/js/Pages/Leadership/Index.tsx exists
 *   [15] Leadership/Index.tsx maps boards + passes initialData to LeadershipBoard
 *   [16] routes/web.php registers GET /leadership → leadership.index → LeadershipBoardController@index
 *   [17] routes/web.php registers GET /leadership-board/{cellId} → leadership-board.show → LeadershipBoardController@show *  [18] AdminSidebar SECTIONS.admin has its canonical 13 admin items (Dashboard/Guests/Impact Cells/Submissions/Reports/Analytics/CSV Import/Notifications/Messages/Users/Roles & Permissions/Audit Log/Settings). Leadership Board is impactCell-scoped, NOT admin-scoped — Phase 06d consolidated nav into role-grouped SECTIONS, so admins no longer see Leadership Board (intentional).
 *  [19] AdminSidebar SECTIONS.impactCell has 'Leadership Board' nav entry referencing leadership.index
 *
 * Run:  /d/php84/php.exe scripts/verify_phase08_run.php
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
    if ($ok) { $pass++; echo "  [{$n}] PASS  {$label}\n"; }
    else { $fail++; $failures[] = "[{$n}] {$label}" . ($detail !== '' ? " — {$detail}" : '');
           echo "  [{$n}] FAIL  {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; }
}

$ctrlPath    = $root . '/app/Http/Controllers/LeadershipBoardController.php';
$boardPath   = $root . '/resources/js/Components/LeadershipBoard.tsx';
$leadIdxPath = $root . '/resources/js/Pages/Leadership/Index.tsx';
$navPath     = $root . '/resources/js/Components/AdminSidebar.tsx';
$routesPath  = $root . '/routes/web.php';

// ─── Controller file-shape checks ────────────────────────────────────────

check(1, 'LeadershipBoardController exists',
    file_exists($ctrlPath),
    $ctrlPath);

$ctrl = file_exists($ctrlPath) ? file_get_contents($ctrlPath) : '';

check(2, 'LeadershipBoardController has index() method',
    preg_match('/function\s+index\s*\(/', $ctrl) === 1);

check(3, 'LeadershipBoardController has show(\$cellId) method',
    preg_match('/function\s+show\s*\([^)]*\$cellId\b/', $ctrl) === 1);

check(4, 'LeadershipBoardController has buildBoardData() method',
    preg_match('/function\s+buildBoardData\s*\(/', $ctrl) === 1);

check(5, 'LeadershipBoardController::index renders Leadership/Index',
    preg_match('/Inertia::render\s*\(\s*[\'"]Leadership\/Index[\'"]/', $ctrl) === 1,
    'expected Inertia::render call with `Leadership/Index` view name');

check(6, 'LeadershipBoardController::index passes boards array to view',
    (bool) preg_match("/([\"']boards[\"']\\s*=>\\s*\\$|boards:\\s*\\$\\w)/", $ctrl));

check(7, 'LeadershipBoardController::index envelope includes generatedAt + fromCache + cacheKey',
    (str_contains($ctrl, "'generatedAt'") || str_contains($ctrl, '"generatedAt"'))
    && (str_contains($ctrl, "'fromCache'") || str_contains($ctrl, '"fromCache"'))
    && (str_contains($ctrl, "'cacheKey'") || str_contains($ctrl, '"cacheKey"')),
    'expected `generatedAt`, `fromCache`, `cacheKey` keys in envelope payload');

check(8, 'LeadershipBoardController::index has role gate (abort/authorize/gate/policy)',
    (bool) preg_match('/abort_unless\s*\(|->authorize\s*\(|Gate::(allow|deny|authorize)\(|abort\(\s*403\b|throw\s+new\s+AuthorizationException/', $ctrl),
    'expected at least one of: abort_unless / authorize() / Gate::* / abort(403) / AuthorizationException');

check(9, 'LeadershipBoardController tile data includes leaderFullName + leaderPhone',
    str_contains($ctrl, 'leaderFullName') && str_contains($ctrl, 'leaderPhone'));

// Strip out the show() body so [10] does not match patterns inside index()
// isolation only matters if both methods share field names; this check
// explicitly guards against raw impact_leaders data passthrough in the
// show() response shape.
$showBody = (preg_match('/function\s+show\s*\([^)]*\$cellId\b.*?(?=function\s|\z)/s', $ctrl, $m) === 1) ? $m[0] : '';

check(10, 'LeadershipBoardController::show exists + does NOT pass raw impact_leaders data',
    $showBody !== ''
    && !preg_match("/[\"'](impact_leaders|impactLeaders)[\"']\\s*=>\\s*\\$/", $showBody)
    && (str_contains($showBody, 'Inertia::render') || str_contains($showBody, 'leadership') || str_contains($showBody, 'board')),
    'expected show() to render a curated view (not raw impact_leaders passthrough)');

// ─── React component + page checks ───────────────────────────────────────

$board = file_exists($boardPath) ? file_get_contents($boardPath) : '';

check(11, 'LeadershipBoard.tsx declares initialData?: BoardData prop',
    preg_match('/initialData\s*\?\s*:\s*BoardData\b/', $board) === 1,
    'expected `initialData?: BoardData` in component prop signature');

check(12, 'LeadershipBoard.tsx skips fetch when initialData provided',
    (bool) preg_match('/useState<\s*BoardData\s*\|\s*null\s*>\s*\(\s*initialData\b/', $board)
    || str_contains($board, 'if (initialData != null)')
    || str_contains($board, 'initialData ?? fetch'),
    'expected useState<BoardData|null>(initialData) or initialData != null guard');

check(13, 'LeadershipBoard.tsx still falls back to fetch when initialData is null',
    preg_match('/useEffect\s*\(|fetch\s*\(|axios\.get\s*\(/', $board) === 1,
    'expected useEffect + fetch/axios fallback for null initialData');

check(14, 'resources/js/Pages/Leadership/Index.tsx exists',
    file_exists($leadIdxPath),
    $leadIdxPath);

$leadIdx = file_exists($leadIdxPath) ? file_get_contents($leadIdxPath) : '';

check(15, 'Leadership/Index.tsx maps boards + passes initialData to LeadershipBoard',
    str_contains($leadIdx, 'boards.map')
    && str_contains($leadIdx, '<LeadershipBoard')
    && (str_contains($leadIdx, 'initialData={') || str_contains($leadIdx, 'initialData =')),
    'expected boards.map loop + <LeadershipBoard initialData={...}/>');

// ─── Routes ───────────────────────────────────────────────────────────────

$routes = file_exists($routesPath) ? file_get_contents($routesPath) : '';

check(16, 'routes/web.php registers GET /leadership → leadership.index → LeadershipBoardController@index',
    preg_match(
        "/Route::get\\(\\s*['\"]\\/leadership['\"]\\s*,.*?LeadershipBoardController::class.*?(?:'index'|,.*?'index'\\)).*?->name\\(\\s*['\"]leadership\\.index['\"]\\s*\\)/s",
        $routes
    ) === 1,
    'expected `Route::get(\'/leadership\', [...LeadershipBoardController::class, \'index\'])->name(\'leadership.index\')`');

check(17, 'routes/web.php registers GET /leadership-board/{cellId} → leadership-board.show → LeadershipBoardController@show',
    preg_match(
        "/Route::get\\(\\s*['\"]\\/leadership-board\\/\\{cellId\\}['\"]\\s*,.*?LeadershipBoardController::class.*?(?:'show'|,.*?'show'\\)).*?->name\\(\\s*['\"]leadership-board\\.show['\"]\\s*\\)/s",
        $routes
    ) === 1,
    'expected `Route::get(\'/leadership-board/{cellId}\', [...LeadershipBoardController::class, \'show\'])->name(\'leadership-board.show\')`');

// ─── AuthenticatedLayout nav entries ──────────────────────────────────────

$nav = file_exists($navPath) ? file_get_contents($navPath) : '';

// Slice SECTIONS.admin.items array literal (Phase 06d consolidated nav into AdminSidebar SECTIONS).
// Anchored end at `]\s*,?\s*}` (section object close, optional comma) so the slice cannot
// accidentally over-capture if a maintainer inserts a `description:` field with bracket
// literals between `label:` and `items:`, AND so the same pattern works whether the
// section is a MID-array entry (with trailing comma) OR the LAST entry (no trailing comma).
// Matches: `key: 'admin', label: 'Administrator', items: [ <items> ] [,] }`.
$adminBranch = (preg_match(
    "/key:\s*'admin',\s*label:\s*'Administrator',\s*items:\s*\[([\s\S]*?)\]\s*,?\s*\}/s",
    $nav, $m) === 1) ? $m[1] : '';

// Slice SECTIONS.impactCell.items array literal (same end-anchor hardening as admin).
// Optional comma handles both mid-array (with trailing `,`) and last-in-array (no trailing `,`).
$cellBranch = (preg_match(
    "/key:\s*'impactCell',\s*label:\s*'Impact Cell Leader',\s*items:\s*\[([\s\S]*?)\]\s*,?\s*\}/s",
    $nav, $m) === 1) ? $m[1] : '';

check(18, 'AdminSidebar SECTIONS.admin has its canonical 13 admin items + does NOT include Leadership Board (impactCell-scoped per Phase 06d design)',
    substr_count($adminBranch, 'routeName:') >= 13
    && str_contains($adminBranch, "'Notifications'")
    && str_contains($adminBranch, 'notification-settings.index')
    && str_contains($adminBranch, "'Audit Log'")
    && !str_contains($adminBranch, "'Leadership Board'"),
    'admin section missing one or more canonical admin items, OR unexpectedly includes Leadership Board (impactCell-scoped per Phase 06d design)');

check(19, 'AdminSidebar SECTIONS.impactCell has Leadership Board nav entry referencing leadership.index',
    str_contains($cellBranch, "'Leadership Board'")
    && (str_contains($cellBranch, "route('leadership.index')") || str_contains($cellBranch, 'leadership.index')),
    'expected `Leadership Board` nav row with `route(\'leadership.index\')` in impactCell section items');

// ─── Summary ──────────────────────────────────────────────────────────────

echo "\n========================================\n";
echo "  Phase 08 Leadership Board UI: {$pass} pass / {$fail} fail\n";
echo "========================================\n";

if ($fail > 0) {
    echo "\nFAILURES:\n";
    foreach ($failures as $f) echo "  - {$f}\n";
    exit(1);
}

exit(0);
