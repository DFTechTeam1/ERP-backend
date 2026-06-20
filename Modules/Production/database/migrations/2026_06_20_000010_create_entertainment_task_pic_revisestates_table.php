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
        Schema::create('entertainment_task_pic_revisestates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')
                ->references('id')
                ->on('entertainment_tasks')
                ->cascadeOnDelete();
            $table->foreignId('work_state_id')
                ->references('id')
                ->on('entertainment_task_pic_workstates')
                ->cascadeOnDelete();
            $table->foreignId('employee_id')
                ->references('id')
                ->on('employees');
            $table->timestamp('assign_at')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('finish_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entertainment_task_pic_revisestates');
    }
};
