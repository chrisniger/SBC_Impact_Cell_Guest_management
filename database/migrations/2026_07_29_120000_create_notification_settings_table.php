<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('action'); // WEEKLY_REPORT_SUBMITTED, GUEST_ASSIGNED
            $table->string('recipient_email');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['action', 'recipient_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
