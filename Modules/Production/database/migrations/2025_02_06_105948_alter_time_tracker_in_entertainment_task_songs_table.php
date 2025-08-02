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
        Schema::table('entertainment_task_songs', function (Blueprint $table) {
            $table->json('time_tracker')->nullable()
                ->comment("will be like: [{type: 'start_working', start_time: '2025-01-10 10:00', 'end_time': null}]. Type will be start_working, start_revise");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entertainment_task_songs', function (Blueprint $table) {
            $table->dropColumn('time_tracker');
        });
    }
};
