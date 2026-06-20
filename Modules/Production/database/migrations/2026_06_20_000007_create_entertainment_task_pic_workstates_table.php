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
        Schema::create('entertainment_task_pic_workstates', function (Blueprint $table) {
            $table->id();
            $table->timestamp('started_at');
            $table->timestamp('first_finish_at')->nullable();
            $table->timestamp('complete_at')->nullable();
            $table->foreignId('task_id')
                ->references('id')
                ->on('entertainment_tasks')
                ->cascadeOnDelete();
            $table->foreignId('employee_id')
                ->references('id')
                ->on('employees');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entertainment_task_pic_workstates');
    }
};
