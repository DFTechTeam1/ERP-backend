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
        Schema::create('entertainment_task_song_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_song_list_id')
                ->references('id')
                ->on('project_song_lists')
                ->cascadeOnDelete();
            $table->foreignId('entertainment_task_song_id')
                ->references('id')
                ->on('entertainment_task_songs')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('project_id')
                ->nullable();
            $table->text('text');
            $table->integer('employee_id')
                ->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entertainment_task_song_logs', function (Blueprint $table) {
            $table->dropForeign(['project_song_list_id']);
            $table->dropForeign(['entertainment_task_song_id']);
            $table->dropForeign(['employee_id']);
        });

        Schema::dropIfExists('entertainment_task_song_logs');
    }
};
