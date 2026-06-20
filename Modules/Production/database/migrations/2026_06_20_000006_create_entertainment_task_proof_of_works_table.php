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
        Schema::create('entertainment_task_proof_of_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')
                ->nullable()
                ->references('id')
                ->on('entertainment_tasks')
                ->cascadeOnDelete();
            $table->string('nas_path', 255)->nullable();
            $table->json('file_path')->nullable();
            $table->foreignId('created_by')
                ->references('id')
                ->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entertainment_task_proof_of_works');
    }
};
