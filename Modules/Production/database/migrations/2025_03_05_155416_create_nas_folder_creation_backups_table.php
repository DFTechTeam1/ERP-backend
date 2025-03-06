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
        Schema::create('nas_folder_creation_backups', function (Blueprint $table) {
            $table->id();
            $table->string('shared_folder');
            $table->year('year');
            $table->string('month_name');
            $table->string('project_name');
            $table->string('prefix_project_name');
            $table->json('child_folders');
            $table->unsignedBigInteger('project_id');
            $table->tinyInteger('status')->default(1)->comment('0 is complete, 1 is ready, 2 is onging, 3 is failed');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nas_folder_creation_backups');
    }
};
