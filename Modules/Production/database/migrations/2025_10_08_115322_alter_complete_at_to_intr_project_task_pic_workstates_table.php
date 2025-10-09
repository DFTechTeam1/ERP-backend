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
        Schema::table('intr_project_task_pic_workstates', function (Blueprint $table) {
            $table->timestamp('complete_at')->nullable()->after('started_at')->comment('When task is being approved by PM, this column should be filled');
            $table->dropColumn('finished_at');
            $table->timestamp('first_finish_at')->nullable()->after('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('intr_project_task_pic_workstates', function (Blueprint $table) {
            $table->dropColumn('complete_at');
            $table->timestamp('finished_at')->nullable()->after('started_at');
            $table->dropColumn('first_finish_at');
        });
    }
};
