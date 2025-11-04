<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->string('trigger_event')->nullable()->after('action');
            $table->json('notification_channel')->nullable()->after('trigger_event');
            $table->json('target_audience')->nullable()->after('trigger_event');
            $table->json('frequency')->nullable()->after('target_audience');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropColumn('notification_channel');
            $table->dropColumn('trigger_event');
            $table->dropColumn('target_audience');
            $table->dropColumn('frequency');
        });
    }
};
