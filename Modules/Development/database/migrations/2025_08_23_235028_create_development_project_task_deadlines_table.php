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
        Schema::create('development_project_task_deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('development_project_tasks')->onDelete('cascade');
            $table->timestamp('deadline');
            $table->timestamp('start_time')->nullable();
            $table->timestamp('actual_end_time')->nullable();
            $table->foreignId('employee_id')
                ->nullable()
                ->constrained('employees')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('development_project_task_deadlines', function (Blueprint $table) {
            $table->dropForeign(['task_id']);
            $table->dropForeign(['employee_id']);
        });
        Schema::dropIfExists('development_project_task_deadlines');
    }
};
