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
        Schema::table('project_task_proof_of_works', function (Blueprint $table) {
            $table->year('created_year')->nullable();
            $table->tinyInteger('created_month')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_task_proof_of_works', function (Blueprint $table) {
            $table->dropColumn('created_year');
            $table->dropColumn('created_month');
        });
    }
};
