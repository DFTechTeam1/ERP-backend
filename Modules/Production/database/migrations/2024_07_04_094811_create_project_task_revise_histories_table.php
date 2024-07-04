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
        Schema::create('project_task_revise_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')
                ->references('id')
                ->on('project_tasks')
                ->cascadeOnDelete();
            $table->integer('project_id')->nullable();
            $table->json('selected_user')->comment('who is working on this revise task')
                ->nullable();
            $table->string('reason')->nullable();
            $table->string('file')->nullable();
            $table->integer('revise_by')->nullable()->comment('who is create this revise reason (base on employee id)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('project_task_revise_histories', function (Blueprint $table) {
            $table->dropForeign(['project_task_id']);
        });
        
        Schema::dropIfExists('project_task_revise_histories');
    }
};
