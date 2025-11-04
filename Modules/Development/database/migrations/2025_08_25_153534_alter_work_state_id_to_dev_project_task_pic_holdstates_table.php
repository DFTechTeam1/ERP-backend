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
        // delete foreign key in the workstates table first
        Schema::table('dev_project_task_pic_workstates', function (Blueprint $table) {
            $table->dropForeign(['related_hold_state']);

            // delete the column
            $table->dropColumn('related_hold_state');
        });

        Schema::table('dev_project_task_pic_holdstates', function (Blueprint $table) {
            $table->bigInteger('work_state_id')->unsigned()->nullable();

            $table->foreign('work_state_id', 'fk_work_state_id')->references('id')->on('dev_project_task_pic_workstates')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // vice versa
        Schema::table('dev_project_task_pic_holdstates', function (Blueprint $table) {
            $table->dropForeign('fk_work_state_id');
            $table->dropColumn('work_state_id');
        });

        Schema::table('dev_project_task_pic_workstates', function (Blueprint $table) {
            $table->foreignId('related_hold_state')->nullable()->constrained('dev_project_task_pic_holdstates')->onDelete('set null');
        });
    }
};
