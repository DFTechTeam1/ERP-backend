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
        Schema::create('development_task_board_changes_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('development_project_tasks')->onDelete('cascade');
            $table->enum('from_board_id', ['sketch', 'animate', 'compose']);
            $table->enum('to_board_id', ['sketch', 'animate', 'compose']);
            $table->foreignId('moved_by')
                ->nullable()
                ->constrained('employees')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign
        Schema::table('development_task_board_changes_histories', function (Blueprint $table) {
            $table->dropForeign(['task_id']);
            $table->dropForeign(['moved_by']);
        });

        Schema::dropIfExists('development_task_board_changes_histories');
    }
};
