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
        Schema::table('intr_project_task_pic_holdstates', function (Blueprint $table) {
            $table->string('reason')->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('intr_project_task_pic_holdstates', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};
