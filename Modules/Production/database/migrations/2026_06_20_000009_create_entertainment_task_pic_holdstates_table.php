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
        Schema::create('entertainment_task_pic_holdstates', function (Blueprint $table) {
            $table->id();
            $table->string('reason', 255);
            $table->timestamp('holded_at')->nullable();
            $table->timestamp('unholded_at')->nullable();
            $table->foreignId('task_id')
                ->references('id')
                ->on('entertainment_tasks')
                ->cascadeOnDelete();
            $table->foreignId('employee_id')
                ->references('id')
                ->on('employees');
            $table->foreignId('work_state_id')
                ->nullable()
                ->references('id')
                ->on('entertainment_task_pic_workstates')
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entertainment_task_pic_holdstates');
    }
};
