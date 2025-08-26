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
        Schema::create('dev_project_task_pic_workstates', function (Blueprint $table) {
            $table->id();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('related_hold_state')->nullable()->constrained('dev_project_task_pic_holdstates')->onDelete('set null');
            $table->foreignId('task_id')->nullable()->constrained('development_project_tasks')->onDelete('set null');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('dev_project_task_pic_workstates', function (Blueprint $table) {
            $table->dropForeign(['related_hold_state']);
            $table->dropForeign(['task_id']);
            $table->dropForeign(['employee_id']);
        });

        Schema::dropIfExists('dev_project_task_pic_workstates');
    }
};
