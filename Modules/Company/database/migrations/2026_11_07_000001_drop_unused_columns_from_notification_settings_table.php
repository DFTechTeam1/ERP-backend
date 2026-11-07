<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the notification template configuration columns that no part of the system reads.
     * trigger_event, target_audience and frequency were added as scaffolding for an event-driven
     * auto-send feature that was never built; they are removed until that feature is designed.
     * notification_channel is intentionally kept.
     */
    public function up(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropColumn(['trigger_event', 'target_audience', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->string('trigger_event')->nullable()->after('action');
            $table->json('target_audience')->nullable()->after('notification_channel');
            $table->json('frequency')->nullable()->after('target_audience');
        });
    }
};
