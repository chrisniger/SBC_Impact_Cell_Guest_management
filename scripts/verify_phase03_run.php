<?php
// Phase 03 end-to-end verification.
//
// Run with:  php scripts/verify_phase03_run.php
//
// As of 2026-07-31 (Phase 13 follow-up): the dev convenience
// APO sub-cell split was retired. The flipped migration
// `2026_07_31_120000_flatten_all_impact_cells_to_primary` bulk-updates
// is_primary=true + parent_cell_id=NULL on every row, and the seeder's
// Step 2 was removed. INSTEAD of the old guarantee of "exactly 4 sub-cells
// under APO", the new invariant is "zero sub-cells, zero non-null parent".
//
// The destructive [7] from the prior version exercised Step 2 by promoting
// 4 rows back to primary + re-seeding + asserting the split re-applied.
// With Step 2 retired that promotion is a no-op (zero rows to promote),
// so [7] is rewritten to verify the constant is empty AND the seeder's
// second run does not mutate any row's is_primary / parent_cell_id.
//
// Asserts:
//   [1] 69 ImpactCell rows seeded
//   [2] primary + sub-cell counts match the new "all-primary" invariant
//   [3] hierarchy invariant under the new flat world
//   [4] APO primary exists + has zero sub-cells (was: 4)
//   [5] ImpactCellController destroy pre-check (still wired — depends on
//       subCells()->exists(); trivially false now but kept as a guardrail
//       in case someone re-introduces sub-cells later)
//   [6] seeder idempotency: re-run does NOT mutate the parent/is_primary
//       columns (Step 2 path is gone)
//   [7] ImpactCellSeeder::APO_SUB_CELL_NAMES is empty (Step 2 retired)
//       + second-run is state-idempotent

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ImpactCell;
use Illuminate\Support\Facades\DB;

$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) {
        echo "  PASS  $label" . ($detail ? "  ($detail)" : '') . "\n";
        $pass++;
    } else {
        echo "  FAIL  $label" . ($detail ? "  ($detail)" : '') . "\n";
        $fail++;
    }
}

echo "=== Phase 03 verification (Phase 13-followup-flatten) ===\n\n";

// [1] 69 ImpactCell rows seeded (68 from Appendix + 1 added "APO")
echo "[1] impact_cells seeded\n";
$total = ImpactCell::count();
check("69 rows seeded", $total === 69, "got {$total}");

// [2] primary + sub-cell counts match the new flat invariant
echo "\n[2] primary / sub-cell distribution (Phase 13 followup: ALL primary)\n";
$primaryCount     = ImpactCell::where('is_primary', true)->count();
$subCellCount     = ImpactCell::where('is_primary', false)->count();
$parentNonNullCnt = ImpactCell::whereNotNull('parent_cell_id')->count();
check('69 primary cells (flatten_all_impact_cells_to_primary ran)',
    $primaryCount === 69,
    "got {$primaryCount}");
check('0 sub-cells (Phase 13 followup retired the dev APO split)',
    $subCellCount === 0,
    "got {$subCellCount}");
check('0 impact_cells has non-null parent_cell_id (every row is detached)',
    $parentNonNullCnt === 0,
    "got {$parentNonNullCnt}");

// [3] Hierarchy invariant under the new flat world
echo "\n[3] hierarchy invariant (every primary has parent_cell_id = NULL; no non-primaries)\n";
$nonPrimaryCount = DB::table('impact_cells')->where('is_primary', false)->count();
$primaryWithParent = DB::table('impact_cells')
    ->where('is_primary', true)
    ->whereNotNull('parent_cell_id')
    ->count();
check('zero non-primary rows',
    $nonPrimaryCount === 0,
    "non_primary={$nonPrimaryCount}");
check('no primary has a parent (parent_cell_id = NULL on every row)',
    $primaryWithParent === 0,
    "primaries_with_parent={$primaryWithParent}");

// [4] APO primary exists + has zero sub-cells (was: 4)
echo "\n[4] APO primary exists + has zero sub-cells\n";
$apo = ImpactCell::where('name', 'APO')->first();
check('APO primary exists', $apo !== null);
if ($apo !== null) {
    check('APO.is_primary = true', $apo->is_primary === true);
    check('APO.parent_cell_id = NULL', $apo->parent_cell_id === null);
    $apoChildrenCount = $apo->subCells()->count();
    check('APO has zero sub-cells (APO split retired)',
        $apoChildrenCount === 0,
        "got {$apoChildrenCount}");
    // Sanity: seed list of APO_SUB_CELL_NAMES is empty (Step 2 has no inputs).
    $subCellNamesSeeded = DB::table('impact_cells')
        ->whereIn('name', Database\Seeders\ImpactCellSeeder::APO_SUB_CELL_NAMES)
        ->count();
    check('no row matches APO_SUB_CELL_NAMES seed list (empty)',
        $subCellNamesSeeded === 0,
        "matched={$subCellNamesSeeded}");
}

// [5] destroy pre-check — subCells()->exists() trivially false now
echo "\n[5] ImpactCellController destroy pre-check (defensive guardrail)\n";
if ($apo !== null) {
    $hasChildren = $apo->subCells()->exists();
    check('APO.subCells()->exists() is FALSE (no sub-cells in scope pre-DB-warning)',
        $hasChildren === false);
}
$leafPrimary = ImpactCell::where('is_primary', true)
    ->whereDoesntHave('subCells')
    ->first();
check('at least one primary with no sub-cells exists (every row qualifies now)',
    $leafPrimary !== null);
if ($leafPrimary !== null) {
    $hasChildren = $leafPrimary->subCells()->exists();
    check("'{$leafPrimary->name}'.subCells()->exists() is FALSE (controller pre-check would pass)",
        $hasChildren === false);
}

// [6] seeder idempotency: re-run does NOT mutate is_primary / parent_cell_id
//         AND does NOT inject APO sub-cells (Step 2 path is gone)
echo "\n[6] seeder idempotency (no mutation on second run; Step 2 retired)\n";
$totalBefore = ImpactCell::count();
$snapshotBefore = DB::table('impact_cells')
    ->select('id', 'is_primary', 'parent_cell_id')
    ->orderBy('id')
    ->get()
    ->toArray();
try {
    (new Database\Seeders\ImpactCellSeeder())->run();
    $totalAfter = ImpactCell::count();
    check("re-running ImpactCellSeeder does NOT add rows (count stayed at {$totalBefore})",
        $totalAfter === $totalBefore,
        "before={$totalBefore} after={$totalAfter}");
    $splitAfter = ImpactCell::where('is_primary', false)->count();
    check('re-running ImpactCellSeeder does NOT re-create sub-cells (still 0)',
        $splitAfter === 0,
        "got {$splitAfter}");
    $snapshotAfter = DB::table('impact_cells')
        ->select('id', 'is_primary', 'parent_cell_id')
        ->orderBy('id')
        ->get()
        ->toArray();
    check('second seeder run is value-identical on (is_primary, parent_cell_id) across all 69 rows (Phase 13 follow-up invariant)',
        // serialize() flips stdClass instances into a deterministic byte
        // string. Without this, PHP `===` would compare object REFERENCES,
        // not values, and would always fail even when the snapshot content
        // is byte-identical (DB::table()->get() creates fresh stdClass
        // instances per call).
        serialize($snapshotBefore) === serialize($snapshotAfter),
        'snapshot values mutated on second run');
} catch (Throwable $e) {
    check('re-running ImpactCellSeeder does NOT throw', false, $e->getMessage());
}

// [7] Step 2 retired — verify the constant is empty + the seeder still
//       produces a stable post-state.
echo "\n[7] APO_SUB_CELL_NAMES seed-list constant is empty + Step-2 path is gone\n";
check('ImpactCellSeeder::APO_SUB_CELL_NAMES constant is empty (no Step-2 inputs)',
    Database\Seeders\ImpactCellSeeder::APO_SUB_CELL_NAMES === [],
    'the Phase 13 followup retired the dev convenience split');
// Idempotency: running the seeder twice in a row must NOT mutate state.
$stateSingleRun = DB::table('impact_cells')
    ->select('id', 'is_primary', 'parent_cell_id')
    ->orderBy('id')
    ->get()
    ->toArray();
(new Database\Seeders\ImpactCellSeeder())->run();
$stateDoubleRun = DB::table('impact_cells')
    ->select('id', 'is_primary', 'parent_cell_id')
    ->orderBy('id')
    ->get()
    ->toArray();
check('two successive seeder runs yield value-identical (is_primary, parent_cell_id) across all 69 rows',
    // See [6] for why serialize() is needed here; without it, fresh
    // stdClass instances returned by DB::table()->get()->toArray()
    // trip PHP's reference-equality semantics.
    serialize($stateSingleRun) === serialize($stateDoubleRun),
    'state values changed between two consecutive runs');

echo "\n=== Summary: $pass pass / $fail fail ===\n";
exit($fail === 0 ? 0 : 1);
