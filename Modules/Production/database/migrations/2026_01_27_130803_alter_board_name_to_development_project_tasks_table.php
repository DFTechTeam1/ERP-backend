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
        Schema::table('development_project_tasks', function (Blueprint $table) {
            $table->enum('board_name', ['sketch', 'animate', 'compose'])->default('sketch')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('development_project_tasks', function (Blueprint $table) {
            $table->dropColumn('board_name');
        });
    }
};
