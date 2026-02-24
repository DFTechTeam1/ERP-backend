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
        Schema::create('development_subtasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid');
            $table->foreignId('project_id')
                ->constrained('development_projects')
                ->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign
        Schema::table('development_subtasks', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['created_by']);
        });

        Schema::dropIfExists('development_subtasks');
    }
};
