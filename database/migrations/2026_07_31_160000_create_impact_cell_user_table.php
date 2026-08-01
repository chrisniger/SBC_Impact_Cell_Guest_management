<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 15 — many-to-many Impact Cell assignments for
 * Impact_Zonal_Coordinator users.
 *
 * Impact_Leaders keep the existing users.impact_cell_id single-cell FK.
 * Zonal coordinators can cover multiple cells, so their assignments live
 * here instead of overloading that leader-only column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impact_cell_user', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->uuid('impact_cell_id');
            $table->foreign('impact_cell_id')
                ->references('id')
                ->on('impact_cells')
                ->cascadeOnDelete();

            $table->primary(['user_id', 'impact_cell_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impact_cell_user');
    }
};
