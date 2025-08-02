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
        Schema::table('entertainment_task_songs', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->references('id')
                ->on('projects');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entertainment_task_songs', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('entertainment_task_songs', function (Blueprint $table) {
            $table->dropColumn('project_id');
        });
    }
};
