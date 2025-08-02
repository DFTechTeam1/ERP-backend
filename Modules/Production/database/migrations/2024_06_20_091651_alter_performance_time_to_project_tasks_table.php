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
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->json('performance_time')->nullable()
                ->comment("will be [{type: 'on_progress', start_at: '2024-05-24 20:00:00', end_at: ''}]. and will be have type: on_progress, review_by_pm, review_by_client");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropColumn('performance_time');
        });
    }
};
