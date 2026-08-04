<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10c — spatie/laravel-activitylog 4.x writes a `batch_uuid` column on
 * every activity row, but the app's original activity_log migration predates
 * that. The vendor stub `add_batch_uuid_column_to_activity_log_table` was
 * never published, so CSV imports (which audit via `activity('csv-import')`)
 * crashed with "Unknown column 'batch_uuid'". This restores the expected
 * schema.
 */
class AddBatchUuidToActivityLogTable extends Migration
{
    public function up(): void
    {
        $connection = config('activitylog.database_connection');
        $table = config('activitylog.table_name');

        Schema::connection($connection)->table($table, function (Blueprint $blueprint) use ($connection, $table) {
            if (! Schema::connection($connection)->hasColumn($table, 'batch_uuid')) {
                $blueprint->uuid('batch_uuid')->nullable()->after('properties');
            }
        });
    }

    public function down(): void
    {
        $connection = config('activitylog.database_connection');
        $table = config('activitylog.table_name');

        if (! Schema::connection($connection)->hasColumn($table, 'batch_uuid')) {
            return;
        }

        Schema::connection($connection)->table($table, function (Blueprint $blueprint) {
            $blueprint->dropColumn('batch_uuid');
        });
    }
}
