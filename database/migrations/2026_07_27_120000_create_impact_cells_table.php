<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impact_cells', function (Blueprint $table) {
            // UUID PK per bridge § 5: $table->uuid('id')->primary();
            $table->uuid('id')->primary();

            $table->string('name')->unique();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();

            // Self-FK. restrictOnDelete per bridge § 5 (NOT nullOnDelete) — sub-cells MUST
            // have a parent per Implementation/04. Controller @destroy pre-checks subCells()
            // and aborts 409 if any exist (see HANDOFF.md § 8 #4 for the pre-check pattern).
            $table->uuid('parent_cell_id')->nullable();
            $table->foreign('parent_cell_id')
                ->references('id')->on('impact_cells')
                ->restrictOnDelete();

            $table->boolean('is_primary')->default(false);
            $table->integer('order')->default(0);

            $table->timestamps();

            $table->index('parent_cell_id');
            $table->index('is_primary');
            $table->index(['is_primary', 'order']);   // primary leadership-board query
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impact_cells');
    }
};