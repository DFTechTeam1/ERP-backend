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
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid');
            $table->foreignId('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();
            $table->foreignId('project_board_id')
                ->references('id')
                ->on('project_boards')
                ->cascadeOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->string('name');
            $table->datetime('start_working_at')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['project_board_id']);
        });
        Schema::dropIfExists('project_tasks');
    }
};
