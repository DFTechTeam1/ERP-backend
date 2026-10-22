<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * task_id is a polymorphic reference to two tables with overlapping ids
     * (project_tasks vs entertainment_task_songs). task_type stores which table
     * it points to so it can be resolved without guessing. Nullable because
     * existing rows are typed by a separate backfill command.
     */
    public function up(): void
    {
        Schema::table('employee_point_project_details', function (Blueprint $table) {
            $table->string('task_type', 20)->nullable()->after('task_id');
            $table->index('task_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_point_project_details', function (Blueprint $table) {
            $table->dropIndex(['task_type']);
            $table->dropColumn('task_type');
        });
    }
};
