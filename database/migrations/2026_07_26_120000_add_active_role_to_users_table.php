<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable; filled by the user picking an active role from the
            // subset of Spatie roles assigned to them. Read by RoleHelper
            // and Form Requests via the User::activeRole() accessor.
            $table->string('active_role')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('active_role');
        });
    }
};
