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
        Schema::create('entertainment_task_pic_approvalstates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pic_id')
                ->references('id')
                ->on('employees');
            $table->foreignId('task_id')
                ->references('id')
                ->on('entertainment_tasks')
                ->cascadeOnDelete();
            $table->foreignId('project_id')
                ->references('id')
                ->on('projects');
            $table->foreignId('work_state_id')
                ->references('id')
                ->on('entertainment_task_pic_workstates')
                ->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entertainment_task_pic_approvalstates');
    }
};
