<?php
// Phase 03 end-to-end verification.
//
// Run with:  php scripts/verify_phase03_run.php
//
// Asserts:
//   [1] 69 ImpactCell rows seeded
//   [2] primary + sub-cell counts match the seeder's design
//   [3] every non-primary has parent_cell_id; every primary has parent_cell_id = null
//   [4] the 4 APO sub-cells are correctly re-parented under the APO primary
//   [5] ImpactCellController destroy pre-check returns 409 for a primary with children
//   [6] re-running the seeder does NOT duplicate cells (idempotent)
//   [7] DESTRUCTIVE: reset 4 APO children to primary, re-run seeder, assert split re-applied
//       (catches regressions in Step 2's guard condition)

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

echo "=== Phase 03 verification ===\n\n";

// [1] 69 ImpactCell rows seeded (68 from Appendix + 1 added "APO" so the split has a parent)
echo "[1] impact_cells seeded\n";
$total = ImpactCell::count();
check("69 rows seeded", $total === 69, "got {$total}");

// [2] primary + sub-cell counts match the seeder's design
echo "\n[2] primary / sub-cell distribution\n";
$primaryCount  = ImpactCell::where('is_primary', true)->count();
$subCellCount  = ImpactCell::where('is_primary', false)->count();
// Expected after the APO split: 69 total. 4 re-parented (non-primary). 65 primary (69 - 4).
check("65 primary cells (69 - 4 APO children re-parented; APO stays primary)",
    $primaryCount === 65,
    "got {$primaryCount}");
check("4 sub-cells (APO-DUTSE, APO RESETTLEMENT, APO RESETTLEMENT B, APO LEGISLATIVE QTRS)",
    $subCellCount === 4,
    "got {$subCellCount}");

// [3] Hierarchy invariant
echo "\n[3] hierarchy invariant (every non-primary has a parent; every primary has parent_cell_id = NULL)\n";
$nonPrimaryWithParent = DB::table('impact_cells')
    ->where('is_primary', false)
    ->whereNotNull('parent_cell_id')
    ->count();
$nonPrimaryWithoutParent = DB::table('impact_cells')
    ->where('is_primary', false)
    ->whereNull('parent_cell_id')
    ->count();
$primaryWithParent = DB::table('impact_cells')
    ->where('is_primary', true)
    ->whereNotNull('parent_cell_id')
    ->count();
check("every non-primary has parent_cell_id",
    $nonPrimaryWithParent === 4 && $nonPrimaryWithoutParent === 0,
    "with_parent={$nonPrimaryWithParent} without_parent={$nonPrimaryWithoutParent}");
check("no primary has a parent (parent_cell_id = NULL)",
    $primaryWithParent === 0,
    "primaries_with_parent={$primaryWithParent}");

// [4] APO sub-cells re-parented
echo "\n[4] APO split — 4 sub-cells under APO primary\n";
$apo = ImpactCell::where('name', 'APO')->first();
check("APO primary exists", $apo !== null);
if ($apo !== null) {
    check("APO.is_primary = true", $apo->is_primary === true);
    check("APO.parent_cell_id = NULL", $apo->parent_cell_id === null);
    $apoChildren = $apo->subCells()->orderBy('name')->pluck('name')->all();
    sort($apoChildren);
    $expectedChildren = ['APO-DUTSE', 'APO LEGISLATIVE QTRS', 'APO RESETTLEMENT', 'APO RESETTLEMENT B'];
    sort($expectedChildren);
    check("APO has exactly these 4 sub-cells: " . implode(',', $expectedChildren),
        $apoChildren === $expectedChildren,
        "got [" . implode(',', $apoChildren) . "]");
}

// [5] Destroy pre-check returns 409 path (we simulate the controller's `if ($cell->subCells()->exists())`)
echo "\n[5] ImpactCellController destroy pre-check\n";
if ($apo !== null) {
    $hasChildren = $apo->subCells()->exists();
    check("subCells()->exists() is true for APO (controller would abort 409)",
        $hasChildren === true);
}
// Pick a primary with no children — should be safe to delete (per pre-check)
$leafPrimary = ImpactCell::where('is_primary', true)
    ->whereDoesntHave('subCells')
    ->first();
if ($leafPrimary !== null) {
    $hasChildren = $leafPrimary->subCells()->exists();
    check("subCells()->exists() is false for '{$leafPrimary->name}' (no 409, safe to delete)",
        $hasChildren === false);
}

// [6] Idempotency: re-running the seeder does NOT duplicate cells
echo "\n[6] seeder idempotency\n";
$totalBefore = ImpactCell::count();
try {
    (new Database\Seeders\ImpactCellSeeder())->run();
    $totalAfter = ImpactCell::count();
    check("re-running ImpactCellSeeder does NOT add rows (count stayed at {$totalBefore})",
        $totalAfter === $totalBefore,
        "before={$totalBefore} after={$totalAfter}");
    // Also assert the split is still applied after re-run (4 sub-cells + APO primary remain)
    $splitAfter = ImpactCell::where('is_primary', false)->count();
    check("re-running ImpactCellSeeder re-applies the APO split (4 sub-cells still non-primary)",
        $splitAfter === 4,
        "got {$splitAfter}");
} catch (Throwable $e) {
    check("re-running ImpactCellSeeder does NOT throw", false, $e->getMessage());
}

// [7] DESTRUCTIVE: reset + re-seed exercises Step 2's re-parenting code path.
// This is the assertion that catches a regression in the
// `if ($sub->parent_cell_id !== $apo->id || $sub->is_primary !== false)` guard.
// Without this, [6] would still pass even if Step 2 was broken (data is already correct from [1]).
echo "\n[7] destructive reset + re-seed (exercises Step 2)\n";
$apoId = ImpactCell::where('name', 'APO')->value('id');
DB::table('impact_cells')
    ->whereIn('name', Database\Seeders\ImpactCellSeeder::APO_SUB_CELL_NAMES)
    ->update(['is_primary' => 1, 'parent_cell_id' => null]);
$promoted = ImpactCell::where('is_primary', true)->count();
check("destructive reset: all 4 APO children now promoted back to primary (69 primary total)",
    $promoted === 69,
    "got {$promoted}");

(new Database\Seeders\ImpactCellSeeder())->run();   // re-applies the split
$reSplit = ImpactCell::where('is_primary', false)->count();
check("Step 2 re-applies split on next run: 4 sub-cells restored under APO",
    $reSplit === 4,
    "got {$reSplit}");

(new Database\Seeders\ImpactCellSeeder())->run();   // restore canonical state for the script's caller

echo "\n=== Summary: $pass pass / $fail fail ===\n";
exit($fail === 0 ? 0 : 1);