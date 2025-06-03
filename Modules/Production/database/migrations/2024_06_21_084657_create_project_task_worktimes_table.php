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
        Schema::create('project_task_worktimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')
                ->references('id')
                ->on('project_tasks')
                ->cascadeOnDelete();
            $table->integer('employee_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->integer('project_id')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->enum('work_type', ['on_progress', 'review_by_pm', 'review_by_client'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_task_worktimes', function (Blueprint $table) {
            $table->dropForeign(['project_task_id']);
        });

        Schema::dropIfExists('project_task_worktimes');
    }
};
