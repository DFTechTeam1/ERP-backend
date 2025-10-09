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
        Schema::table('project_task_duration_histories', function (Blueprint $table) {
            $table->string('task_type')->after('task_id')->comment("Will be 'interactive' or 'production'"); // new column for task type
            $table->boolean('is_interactive')->after('total_task_revised')->default(false); // new column for interactive flag

            // remove task id foreign key
            $foreignKeys = Schema::getForeignKeys('project_task_duration_histories');
            foreach ($foreignKeys as $foreignKey) {
                if ($foreignKey['columns'][0] === 'task_id') {
                    $table->dropForeign(['task_id']);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_task_duration_histories', function (Blueprint $table) {
            $table->dropColumn('task_type');
            $table->dropColumn('is_interactive');

            // re-add task id foreign key
            // $table->foreign('task_id')->references('id')->on('intr_project_tasks')->onDelete('cascade');
        });
    }
};
