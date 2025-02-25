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
        Schema::create('project_task_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->references('id')
                ->on('employees');
            $table->foreignId('project_task_id')
                ->references('id')
                ->on('project_tasks');
            $table->foreignId('project_board_id')
                ->references('id')
                ->on('project_boards');
            $table->foreignId('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_task_states', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['project_task_id']);
            $table->dropForeign(['project_board_id']);
            $table->dropForeign(['project_id']);
        });

        Schema::dropIfExists('project_task_states');
    }
};
