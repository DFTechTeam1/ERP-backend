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
        Schema::table('employee_overtimes', function (Blueprint $table) {
            $table->dropForeign(['task_id']);
            $table->dropColumn('task_id');
            $table->json('task_id')->nullable()
                ->comment("e.g. [1.2,3]");
            $table->json('task_name')->nullable()
                ->change()
                ->comment("e.g. ['task name 1', 'task name 2']");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_overtimes', function (Blueprint $table) {
            $table->string('task_name')->comment('snapshot task name')->nullable()->change();

            $table->dropColumn('task_id');

            $table->foreignId('task_id')
                ->nullable()
                ->constrained('project_tasks')
                ->nullOnDelete();
        });
    }
};
