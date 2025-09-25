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
        Schema::create('intr_project_task_deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('intr_project_tasks')->onDelete('cascade');
            $table->timestamp('deadline')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('actual_end_time')->nullable();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('intr_project_task_deadlines', function (Blueprint $table) {
            $table->dropForeign(['task_id']);
            $table->dropForeign(['employee_id']);
        });
        Schema::dropIfExists('intr_project_task_deadlines');
    }
};
