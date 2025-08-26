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
        Schema::create('dev_project_task_revise_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revise_id')->constrained('dev_project_task_revises')->onDelete('cascade');
            $table->string('image_path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('dev_project_task_revise_images', function (Blueprint $table) {
            $table->dropForeign(['revise_id']);
        });
        Schema::dropIfExists('dev_project_task_revise_images');
    }
};
