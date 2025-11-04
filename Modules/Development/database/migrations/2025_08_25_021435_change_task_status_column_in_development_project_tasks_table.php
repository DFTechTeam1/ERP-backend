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

        Schema::table('development_project_tasks', function (Blueprint $table) use ($taskStatus) {
            $table->enum('status', $taskStatus)->default(TaskStatus::Draft->value)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $taskStatus = TaskStatus::cases();
        $taskStatus = collect($taskStatus)->map(fn ($status) => $status->value)->toArray();

        Schema::table('development_project_tasks', function (Blueprint $table) use ($taskStatus) {
            $table->enum('status', $taskStatus)->default(TaskStatus::Draft->value)->change();
        });
    }
};
