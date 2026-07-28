<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 06e+1 — Admin Users CRUD prerequisites.
 *
 * Adds two columns to `users`:
 *
 *   - `last_seen_at` (nullable timestamp): updated by `RecordLastSeen`
 *     middleware on every authenticated request, throttled to one
 *     touch per user per 5 minutes (cache-guarded) so high-frequency
 *     Inertia navigations don't thrash the table. Backs the
 *     "Last seen" column on the Admin/Users page (real last activity,
 *     not `updated_at` proxy).
 *
 *   - `deleted_at` (nullable timestamp): adds Laravel's `SoftDeletes`
 *     trait support to the User model so `User::delete()` becomes a
 *     reversible tombstone instead of a hard cascade. Protects
 *     `guests.follow_officer_id` and `impact_submissions.created_by`
 *     foreign keys from nulling on hard-delete, and leaves a clean
 *     audit trail for Phase 11 (Reports + Audit).
 *
 * Both columns are nullable so DOWN() is safe and existing rows pass
 * without backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // last_seen_at — throttled touch by RecordLastSeen middleware
            // (5-minute cache guard). Nullable so existing users show
            // '—' in the column until they take their first action.
            $table->timestamp('last_seen_at')->nullable()->after('active_role');

            // SoftDeletes tombstone — used by User::delete() (admin
            // delete from Admin/Users/Index); kept on the table so
            // defaulted queries continue to filter out deleted rows
            // (no app-side `whereNull('deleted_at')` sprinkling needed).
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('last_seen_at');
        });
    }
};
