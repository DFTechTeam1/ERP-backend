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
        // drop current table
        Schema::dropIfExists('nas_folder_creations');

        Schema::create('nas_folder_creations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('project_name', 255);
            $table->date('project_date');
            $table->string('host', 255);
            $table->string('shared_folder',255);
            $table->string('root_path', 255);
            $table->string('base_path', 255);
            $table->enum('status', ['queue', 'processing', 'success', 'failed'])->default('queue');
            $table->enum('type', ['create', 'rename', 'delete'])->default('create');
            $table->string('current_parent_dir_path')->nullable()
                ->comment('Current parent directory path');
            $table->string('target_parent_dir_path')->nullable()
                ->comment('Target parent directory path');
            $table->json('current_child_dir_paths')->nullable()
                ->comment('List of current child directories');
            $table->json('target_child_dir_paths')->nullable()
                ->comment('List of target child directories');
            $table->bigInteger('response_code');
            $table->json('failed_reason')->nullable()
                ->comment('Failure details or error info');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('nas_folder_creations', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::dropIfExists('nas_folder_creations');
    }
};
