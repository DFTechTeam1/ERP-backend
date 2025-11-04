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
        Schema::create('dev_project_task_pic_holdstates', function (Blueprint $table) {
            $table->id();
            $table->timestamp('holded_at')->nullable();
            $table->timestamp('unholded_at')->nullable();
            $table->foreignId('task_id')->nullable()->constrained('development_project_tasks')->onDelete('cascade');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('dev_project_task_pic_holdstates', function (Blueprint $table) {
            $table->dropForeign(['task_id']);
            $table->dropForeign(['employee_id']);
        });

        Schema::dropIfExists('dev_project_task_pic_holdstates');
    }
};
