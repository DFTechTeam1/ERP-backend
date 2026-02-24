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
        Schema::create('development_subtask_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subtask_id')
                ->constrained('development_subtasks')
                ->onDelete('cascade');
            $table->string('note')->nullable();
            $table->foreignId('uploaded_by')
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
        Schema::table('development_subtask_uploads', function (Blueprint $table) {
            $table->dropForeign(['subtask_id']);
            $table->dropForeign(['uploaded_by']);
        });

        Schema::dropIfExists('development_subtask_uploads');
    }
};
