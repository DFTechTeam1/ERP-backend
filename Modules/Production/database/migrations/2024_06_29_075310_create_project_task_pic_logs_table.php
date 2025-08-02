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
        Schema::create('project_task_pic_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')
                ->references('id')
                ->on('project_tasks')
                ->cascadeOnDelete();
            $table->integer('employee_id');
            $table->string('work_type', 30)->comment('will be: assigned, on_progress, check_by_pm, revise, finish');
            $table->timestamp('time_added');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_task_pic_logs', function (Blueprint $table) {
            $table->dropForeign(['project_task_id']);
        });

        Schema::dropIfExists('project_task_pic_logs');
    }
};
