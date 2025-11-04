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
        Schema::create('dev_project_task_pic_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('development_project_tasks')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->boolean('is_until_finish')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('dev_project_task_pic_histories', function (Blueprint $table) {
            $table->dropForeign(['task_id']);
            $table->dropForeign(['employee_id']);
        });
        Schema::dropIfExists('dev_project_task_pic_histories');
    }
};
