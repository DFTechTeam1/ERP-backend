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
        Schema::create('development_project_task_attachments', function (Blueprint $table) {
            $table->id();
            $table->char('uid', 36);
            $table->foreignId('task_id')->constrained(table: 'development_project_tasks')->onDelete('cascade');
            $table->string('file_path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // delete foreign
        Schema::table('development_project_task_attachments', function (Blueprint $table) {
            $table->dropForeign(['task_id']);
        });
        Schema::dropIfExists('development_project_task_attachments');
    }
};
