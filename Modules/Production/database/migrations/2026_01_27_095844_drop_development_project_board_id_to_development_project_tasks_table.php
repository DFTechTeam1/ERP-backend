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
            // Drop foreign
            if (Schema::hasIndex('development_project_tasks', 'development_project_tasks_development_project_board_id_foreign')) {
                $table->dropForeign(['development_project_board_id']);
            }

            $table->dropColumn('development_project_board_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('development_project_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('development_project_board_id')->nullable();
        });
    }
};
