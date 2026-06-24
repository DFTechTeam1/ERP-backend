<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Replace the global unique index on song_name with a composite unique on
     * (project_song_id, song_name) so the same song title may exist across
     * different groups/projects while staying unique within a single group.
     * This also matches the uniqueBy used by ProjectSongRepository::storeSongs().
     */
    public function up(): void
    {
        Schema::table('project_song_items', function (Blueprint $table) {
            $table->dropUnique('project_song_items_song_name_unique');
            $table->unique(['project_song_id', 'song_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_song_items', function (Blueprint $table) {
            $table->dropUnique(['project_song_id', 'song_name']);
            $table->unique('song_name');
        });
    }
};
