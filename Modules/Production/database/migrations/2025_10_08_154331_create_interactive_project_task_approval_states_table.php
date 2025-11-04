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
        Schema::create('intr_project_task_approval_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pic_id')
                ->constrained('employees')
                ->onDelete('cascade');
            $table->foreignId('task_id')
                ->constrained('intr_project_tasks')
                ->onDelete('cascade');
            $table->foreignId('project_id')
                ->constrained('interactive_projects')
                ->onDelete('cascade');
            $table->foreignId('work_state_id')
                ->constrained('intr_project_task_pic_workstates', 'id', 'workstate_id')
                ->onDelete('cascade');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('intr_project_task_approval_states', function (Blueprint $table) {
            // $table->dropForeign(['pic_id']);
            // $table->dropForeign(['task_id']);
            // $table->dropForeign(['project_id']);
            $table->dropForeign('workstate_id');
        });
        Schema::dropIfExists('intr_project_task_approval_states');
    }
};
