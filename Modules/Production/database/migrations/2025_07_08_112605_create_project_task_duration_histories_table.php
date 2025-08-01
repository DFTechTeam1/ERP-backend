<?php

use App\Enums\Production\TaskHistoryType;
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
        $types = TaskHistoryType::cases();
        $types = collect($types)->map(function ($item) {
            return $item->value;
        })->toArray();

        Schema::create('project_task_duration_histories', function (Blueprint $table) use ($types) {
            $table->id();
            $table->foreignId('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();
            $table->foreignId('task_id')
                ->references('id')
                ->on('project_tasks')
                ->cascadeOnDelete();
            $table->foreignId('pic_id')
                ->references('id')
                ->on('employees')
                ->cascadeOnDelete();
            $table->bigInteger('task_duration');
            $table->bigInteger('pm_approval_duration')->nullable();
            $table->enum('task_type', $types);
            $table->boolean('is_task_revised')->default(false);
            $table->boolean('is_task_deadline_updated')->default(false);
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_task_duration_histories', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['task_id']);
            $table->dropForeign(['pic_id']);
        });

        Schema::dropIfExists('project_task_duration_histories');
    }
};
