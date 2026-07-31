<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13 — adds 6 free-text leadership-team columns to `impact_cells`.
 *
 * Why free-text (not FK to users)
 * -------------------------------
 * Per Implementation/04 + the implementation policy decision in this
 * turn, the cell's leadership team is stored as plain text on the cell
 * row. Impact_Leaders register via the public signup form and SEED the
 * leader/assistant/welfare info on the cell they pick; admins can
 * later correct it on the Show page (existing /impact-cells/{id} PUT
 * route, extended in Phase B). FK columns were considered but rejected
 * because:
 *   1. Assistant + Welfare Officer are not always accounts in the
 *      system — they're names that the cell leadership knows by
 *      reputation, not users that need to log in.
 *   2. Sign-up-time seeding needs to write to whatever cell the new
 *      leader picks regardless of whether accounts exist.
 *   3. Keeps `impact_cells` migration surface small and lets the
 *      UI's inline-edit mode (Phase C) work without a join.
 *
 * Column choice
 * -------------
 *   - leader_name              (varchar(255))
 *   - leader_phone             (varchar(32))
 *   - assistant_name           (varchar(255))
 *   - assistant_phone          (varchar(32))
 *   - welfare_officer_name     (varchar(255))
 *   - welfare_officer_phone    (varchar(32))
 *
 * All nullable so the existing 69 seeded cells (which have no leader
 * team assigned yet) survive the migration without backfill. The Show
 * page falls back to '—' for each placeholder (Phase C).
 *
 * Run order: 2026_07_30_120001_add_impact_cell_id_to_users_table runs
 * after this one (higher timestamp) but they are independent — no
 * cross-FK to coordinate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impact_cells', function (Blueprint $table) {
            $table->string('leader_name')->nullable()->after('address');
            $table->string('leader_phone', 32)->nullable()->after('leader_name');
            $table->string('assistant_name')->nullable()->after('leader_phone');
            $table->string('assistant_phone', 32)->nullable()->after('assistant_name');
            $table->string('welfare_officer_name')->nullable()->after('assistant_phone');
            $table->string('welfare_officer_phone', 32)->nullable()->after('welfare_officer_name');
        });
    }

    public function down(): void
    {
        Schema::table('impact_cells', function (Blueprint $table) {
            $table->dropColumn([
                'leader_name',
                'leader_phone',
                'assistant_name',
                'assistant_phone',
                'welfare_officer_name',
                'welfare_officer_phone',
            ]);
        });
    }
};
