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
        Schema::create('entertainment_task_duration_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->references('id')
                ->on('projects');
            $table->foreignId('task_id')
                ->references('id')
                ->on('entertainment_tasks')
                ->cascadeOnDelete();
            $table->string('task_type', 255)->comment('song | production');
            $table->foreignId('pic_id')
                ->references('id')
                ->on('employees')
                ->comment('should be entertainment project manager');
            $table->foreignId('employee_id')
                ->references('id')
                ->on('employees');
            $table->bigInteger('task_full_duration')->default(0);
            $table->bigInteger('task_holded_duration')->default(0);
            $table->bigInteger('task_revised_duration')->default(0);
            $table->bigInteger('task_actual_duration')->default(0);
            $table->bigInteger('task_approval_duration')->default(0);
            $table->bigInteger('total_task_holded')->default(0);
            $table->bigInteger('total_task_revised')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entertainment_task_duration_histories');
    }
};
