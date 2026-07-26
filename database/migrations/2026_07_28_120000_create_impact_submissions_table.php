<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impact_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('impact_cell_id');
            $table->foreign('impact_cell_id')->references('id')->on('impact_cells')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type'); // member, report, childbirth, soul
            $table->json('data');
            $table->string('fellowship_date_key')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['impact_cell_id', 'fellowship_date_key'], 'submission_cell_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impact_submissions');
    }
};
