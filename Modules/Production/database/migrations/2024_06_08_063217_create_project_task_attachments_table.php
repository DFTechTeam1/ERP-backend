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
        Schema::create('project_task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')
                ->references('id')
                ->on('project_tasks')
                ->cascadeOnDelete();
            $table->foreignId('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();
            $table->string('media')->nullable();
            $table->string('display_name')->nullable();
            $table->integer('related_task_id')->nullable();
            $table->tinyInteger('type')
                ->comment('1 for media, 2 for other task link, 3 for external link');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_task_attachments', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['project_task_id']);
        });
        Schema::dropIfExists('project_task_attachments');
    }
};
