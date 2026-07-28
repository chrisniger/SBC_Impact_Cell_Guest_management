<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10b+ — `guests.email` column reconciliation.
 *
 * The `CsvColumns::forRole()` export list AND the `CsvImportController`
 * aliasMap have always included `'email'` as a guest-level column. The
 * original `guests` migration (Phase 04, 2026-07-27) never actually
 * created the column on the table, so `GET /csv/export` 500's at runtime
 * with:
 *
 *   SQLSTATE[42S22]: Column not found: 1054 Unknown column 'email'
 *   in 'field list'
 *
 * This migration adds the missing `email` column (nullable string, max 255)
 * to align the schema with the wire-format contracts in:
 *  - app/Support/CsvColumns.php  (forRole + aliasesForTemplate)
 *  - app/Http/Controllers/CsvImportController.php (header → model binding)
 *  - scripts/verify_phase10_run.php  ([14] regression: must contain 'email')
 *  - scripts/verify_phase10b_run.php ([6]  regression: must contain 'email')
 *
 * Nullable (no default) so existing rows pass through without backfill. CSV
 * importers will populate `email` going forward; legacy rows export as an
 * empty cell — which matches the previous "no email column" reality for
 * those records.
 *
 * Placement: `after('phone')` — groups `email` next to the other contact-
 * info columns (`phone`, `address`) for readability when inspecting the
 * schema or generating SHOW CREATE TABLE output.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};