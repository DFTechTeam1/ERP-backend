<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Intervention\Image\Colors\Rgb\Channels\Blue;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('entertainment_task_song_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')
                ->references('id')
                ->on('entertainment_task_songs')
                ->cascadeOnDelete();
            $table->string('nas_path', 255);
            $table->unsignedBigInteger('employee_id');
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entertainment_task_song_results', function (Blueprint $table) {
            $table->dropForeign(['task_id']);
        });

        Schema::dropIfExists('entertainment_task_song_results');
    }
};
