<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 04 — Guest records.
 *
 * Columns mirror Implementation/02_Database_Schema.md `model Guest { … }`:
 *   - UUID PK (per bridge § 5)
 *   - `date` defaults to NOW()
 *   - `contacted_status` + `join_when` are stored as plain strings (the v2 enum
 *     values `AvailableForVisit`, `FirstTimer`, etc. are PascalCase strings —
 *     keeping them as strings avoids a MySQL ENUM type change trap and matches
 *     bridge § 5's permissive approach to the ContactedStatus/JoinWhen enums)
 *   - `follow_up_contacts` is JSON (max 3 sections per the matrix)
 *   - `comments` + `feedback` are longText
 *   - `softDeletes()` for `deleted_at` (mirrors `Guest.deletedAt`)
 *   - `nearest_impact_cell_id` FK nullable → impact_cells.id with `restrictOnDelete`
 *     (consistent with the ImpactCell self-FK convention)
 *   - `follow_officer_id` FK nullable → users.id with `restrictOnDelete` (a user
 *     with assigned guests cannot be hard-deleted; the controller soft-deletes
 *     instead via the User model's SoftDeletes trait pattern, via Phase 02)
 *
 * Indexes match the v2 schema:
 *   - [follow_officer_id, deleted_at] (per-user list query)
 *   - [contacted_status] (Follow Up Officer filter)
 *   - [follow_up_status] (Follow Up Team filter)
 *   - [nearest_impact_cell] (Impact Cell leader scope)
 *   - [event], [source], [deleted_at] (general filter / soft-delete)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            // UUID PK per bridge § 5
            $table->uuid('id')->primary();

            // Core record
            $table->dateTime('date')->useCurrent();
            $table->string('event')->nullable();
            $table->string('event_other')->nullable();
            $table->string('guest_name');         // required; no default
            $table->string('source')->nullable();

            // Demographics (Follow Up Officer group)
            $table->string('gender')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('age')->nullable();    // String per v2 schema
            $table->string('phone')->nullable();
            $table->string('address')->nullable();

            // Impact Cell group
            $table->uuid('nearest_impact_cell_id')->nullable();
            $table->foreign('nearest_impact_cell_id')
                ->references('id')->on('impact_cells')
                ->restrictOnDelete();
            $table->string('impact_status')->nullable();

            // Follow Up Officer group — contact + visitation
            $table->string('contacted_status')->nullable();   // ContactedStatus enum as string
            $table->string('join_when')->nullable();          // JoinWhen enum as string
            $table->string('days_available')->nullable();
            $table->longText('comments')->nullable();
            $table->boolean('visited')->default(false);
            $table->string('visited_at')->nullable();
            $table->string('indicated_to_join')->nullable();
            $table->string('visitation_status')->nullable();
            $table->longText('feedback')->nullable();

            // Follow Up Team group
            $table->string('follow_up_status')->nullable();
            $table->json('follow_up_contacts')->nullable();

            // Assignment (foreignId = unsignedBigInteger to match `users.id`)
            $table->foreignId('follow_officer_id')->nullable();
            $table->foreign('follow_officer_id')
                ->references('id')->on('users')
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();   // adds `deleted_at` (mirrors `Guest.deletedAt`)

            // Indexes per Implementation/02_Database_Schema.md `Guest @@index([…])`
            $table->index(['follow_officer_id', 'deleted_at']);
            $table->index('contacted_status');
            $table->index('follow_up_status');
            $table->index('nearest_impact_cell_id');
            $table->index('event');
            $table->index('source');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
