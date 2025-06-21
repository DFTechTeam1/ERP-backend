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
        Schema::create('project_task_pics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')
                ->references('id')
                ->on('project_tasks')
                ->cascadeOnDelete();
            $table->foreignId('employee_id')
                ->references('id')
                ->on('employees')
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_task_pics', function (Blueprint $table) {
            $table->dropForeign(['project_task_id']);
            $table->dropForeign(['employee_id']);
        });

        Schema::dropIfExists('project_task_pics');
    }
};
