<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13 — `users.impact_cell_id` FK to `impact_cells.id`.
 *
 * Sourced from Implementation/04_Impact_Cell_Hierarchy.md as the
 * "spiritual inverse" of a cell's `parent_cell_id`: same UUID FK style,
 * same `restrictOnDelete` for the same reasons.
 *
 * Why one column per user (not many-to-many)
 * -----------------------------------------
 * A leader can only lead ONE cell in the current product surface. If
 * multi-cell leadership ever ships (Phase 14+), the schema flips to a
 * join table `cell_leaders(cell_id, user_id, role_label)` and this
 * single FK would be dropped. Until then, one column is the simplest
 * thing that matches the "one cell per user" worldview.
 *
 * Why `restrictOnDelete`
 * ----------------------
 * A user who is the leader of cell X cannot be silently orphaned by a
 * delete that nulls the cell. Conversely, deleting cell X with an
 * assigned leader MUST be a deliberate admin action (Impact Cell
 * policy already enforces this through the controller's pre-check +
 * 409 logic for sub-cells; this FK extends the same guarantee to
 * leader-users). Plain `nullOnDelete` would silently drop the user's
 * assigned cell on impact_cells destroy, which loses information.
 *
 * `ImpactCell::destroy()` does NOT yet pre-check `users.impact_cell_id`
 * because today we don't have a delete-cascade flow for cells; the
 * DB-level restrict is the defense-in-depth (same pattern as the
 * `parent_cell_id` self-FK). Phase 14 may add the controller pre-check.
 *
 * Index choice
 * ------------
 * Indexed because we expect leader-by-cell lookups
 * (User::scopeAssignedToCell($cellId) — Phase B) to be the hot path
 * for dashboards.
 *
 * Column position
 * ---------------
 * After `active_role` so the admin/users pages still read top-down
 * (auth/permissions cluster).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('impact_cell_id')->nullable()->after('active_role');

            $table->foreign('impact_cell_id')
                ->references('id')->on('impact_cells')
                ->restrictOnDelete();

            $table->index('impact_cell_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['impact_cell_id']);
            $table->dropIndex(['impact_cell_id']);
            $table->dropColumn('impact_cell_id');
        });
    }
};
