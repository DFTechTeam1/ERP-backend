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
        Schema::create('entertainment_task_song_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entertainment_task_id')
                ->references('id')
                ->on('entertainment_tasks')
                ->cascadeOnDelete();
            $table->foreignId('song_item_id')
                ->references('id')
                ->on('project_song_items')
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entertainment_task_song_items');
    }
};
