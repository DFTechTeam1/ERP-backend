<?php

use App\Enums\Development\Project\Task\TaskStatus;
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
        $taskStatus = TaskStatus::cases();
        $taskStatus = collect($taskStatus)->map(fn ($status) => $status->value)->toArray();
        Schema::create('development_project_tasks', function (Blueprint $table) use ($taskStatus) {
            $table->id();
            $table->foreignId('development_project_id')
                ->constrained('development_projects')
                ->onDelete('cascade');
            $table->foreignId('development_project_board_id')
                ->constrained('development_project_boards')
                ->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', $taskStatus);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('development_project_tasks', function (Blueprint $table) {
            $table->dropForeign(['development_project_id']);
            $table->dropForeign(['development_project_board_id']);
        });

        Schema::dropIfExists('development_project_tasks');
    }
};
