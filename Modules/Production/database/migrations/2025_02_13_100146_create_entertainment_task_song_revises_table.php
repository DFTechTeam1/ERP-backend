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
        Schema::create('entertainment_task_song_revises', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_song_list_id');
            $table->unsignedBigInteger('entertainment_task_song_id');
            $table->string('reason');
            $table->json('images')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entertainment_task_song_revises');
    }
};
