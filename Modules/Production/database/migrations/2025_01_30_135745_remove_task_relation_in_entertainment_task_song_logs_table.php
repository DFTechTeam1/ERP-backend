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
        Schema::table('entertainment_task_song_logs', function (Blueprint $table) {
            if (checkForeignKey(tableName: 'entertainment_task_song_logs', columnName: 'entertainment_task_song_id')) {
                $table->dropForeign(['entertainment_task_song_id']);
            }
            
            $table->bigInteger('entertainment_task_song_id')
                ->default(0)
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entertainment_task_song_logs', function (Blueprint $table) {
            $table->bigInteger('entertainment_task_song_id')->nullable()
                ->change();
        });
    }
};
