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
        Schema::create('intr_project_task_revises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('intr_project_tasks')->onDelete('cascade');
            $table->string('reason')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('intr_project_task_revises', function (Blueprint $table) {
            $table->dropForeign(['task_id']);
            $table->dropForeign(['assigned_by']);
        });
        Schema::dropIfExists('intr_project_task_revises');
    }
};
