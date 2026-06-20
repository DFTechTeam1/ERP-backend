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
        Schema::create('entertainment_task_deadlines_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')
                ->nullable()
                ->references('id')
                ->on('entertainment_tasks')
                ->cascadeOnDelete();
            $table->timestamp('deadline');
            $table->unsignedBigInteger('deadline_change_reason_id')
                ->nullable()
                ->comment('0 if user input custom reason');
            $table->string('custom_reason', 255)->nullable();
            $table->foreignId('created_by')
                ->nullable()
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
        Schema::dropIfExists('entertainment_task_deadlines_histories');
    }
};
