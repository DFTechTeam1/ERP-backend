<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_task_holds', function (Blueprint $table) {
            $table->bigInteger('employee_id')
                ->after('hold_by')
                ->nullable();
        });

        // migrate all values
        DB::table('project_task_holds')
            ->where('id', '>', 0)
            ->update(['employee_id' => DB::raw('hold_by')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_task_holds', function (Blueprint $table) {
            $table->dropColumn('employee_id');
        });
    }
};
