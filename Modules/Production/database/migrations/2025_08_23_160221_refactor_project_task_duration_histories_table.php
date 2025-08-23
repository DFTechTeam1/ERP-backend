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
        Schema::table('project_task_duration_histories', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['task_id']);
            $table->dropForeign(['pic_id']);
        });
        Schema::dropIfExists('project_task_duration_histories');

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
            $table->foreignId('employee_id')
                ->references('id')
                ->on('employees')
                ->cascadeOnDelete();
            $table->enum('task_type', $types);

            $table->bigInteger('task_full_duration')->default(0);
            $table->bigInteger('task_holded_duration')->default(0);
            $table->bigInteger('task_revised_duration')->default(0);
            $table->bigInteger('task_actual_duration')->default(0);
            $table->bigInteger('task_approval_duration')->default(0);

            $table->bigInteger('total_task_holded')->default(0);
            $table->bigInteger('total_task_revised')->default(0);

            $table->timestamps();
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
};
