<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 13 follow-up — flatten the impact_cells hierarchy.
 *
 * Per the product decision (2026-07-31): every impact cell is now a
 * primary cell, period. There is no parent / sub-cell relationship
 * active in the project. The ImpactCells/Index, Edit, and Show pages
 * all rely on `is_primary = true` and `parent_cell_id = NULL` —
 * sub-cell rendering and the dev-only APO split are no longer in
 * scope.
 *
 * What this migration does
 * ------------------------
 *  - Bulk-updates every row in `impact_cells` to:
 *      * `is_primary`     = true
 *      * `parent_cell_id` = NULL
 *  - Idempotent: running it twice is a no-op (no FK swaps, no value
 *    deltas once the first run completes).
 *
 * Why is the down() a no-op?
 *  - The pre-flatten parent_cell_id mapping is no longer recoverable
 *    from the migrated rows.
 *  - Adding sub-cell hierarchy back later should be a NEW forward
 *    migration that explicitly re-creates whatever hierarchy is
 *    desired at that time.
 *
 * Upstream / downstream
 *  - ImpactCellSeeder::run() Step 2 (the APO sub-cell re-parenting)
 *    was neutralized in this turn so `migrate:fresh --seed` runs
 *    yield all-primary cells without re-introducing sub-cells.
 *  - Auth/RegisteredUserController::create() and
 *    Admin/UserController::edit() filter cellsList to is_primary=true
 *    so the Inertia payload never carries sub-cell options even if
 *    the migration has not been run.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('impact_cells')->update([
            'is_primary'     => true,
            'parent_cell_id' => null,
        ]);
    }

    public function down(): void
    {
        // No-op. See class docblock — recovery is a future forward
        // migration, not a backward one.
    }
};
