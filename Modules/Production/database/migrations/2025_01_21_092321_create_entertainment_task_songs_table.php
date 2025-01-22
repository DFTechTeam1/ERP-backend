<?php

use App\Enums\Production\TaskSongStatus;
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
        $taskSongStatus = TaskSongStatus::cases();
        $taskSongStatus = collect($taskSongStatus)->map(function ($item) {
            return $item->value;
        })->toArray();

        Schema::create('entertainment_task_songs', function (Blueprint $table) use ($taskSongStatus) {
            $table->id();
            $table->foreignId('project_song_list_id')
                ->references('id')
                ->on('project_song_lists')
                ->cascadeOnDelete();
            $table->enum('status', $taskSongStatus);
            $table->foreignId('employee_id')
                ->references('id')
                ->on('employees');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entertainment_task_songs', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['project_song_list_id']);
        });

        Schema::dropIfExists('entertainment_task_songs');
    }
};
