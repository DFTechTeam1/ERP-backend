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
        Schema::create('intr_project_task_attachments', function (Blueprint $table) {
            $table->id();
            $table->char('uid', 36)->unique();
            $table->foreignId('intr_project_task_id')->constrained('intr_project_tasks')->onDelete('cascade');
            $table->string('file_path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('intr_project_task_attachments', function (Blueprint $table) {
            $table->dropForeign(['intr_project_task_id']);
        });
        Schema::dropIfExists('intr_project_task_attachments');
    }
};
