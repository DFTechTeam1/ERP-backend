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
        Schema::table('project_task_deadlines', function (Blueprint $table) {
            if (checkForeignKey(tableName: 'project_task_deadlines', columnName: 'due_reason')) {
                $table->dropForeign(['due_reason']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_task_deadlines', function (Blueprint $table) {
            if (checkForeignKey(tableName: 'project_task_deadlines', columnName: 'due_reason')) {
                $table->dropForeign(['due_reason']);
            }
        });
    }
};
