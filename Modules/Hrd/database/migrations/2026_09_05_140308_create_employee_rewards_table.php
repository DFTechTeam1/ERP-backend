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
        Schema::create('employee_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees', 'id');
            $table->foreignId('project_id')->constrained('projects', 'id');
            $table->foreignId('employee_point_project_id')->constrained('employee_point_projects', 'id');
            $table->decimal('base_reward', 24, 2)->default(0);
            $table->integer('total_point')->default(0);
            $table->integer('point')->default(0);
            $table->integer('additional_point')->default(0);
            $table->decimal('total_reward', 24, 2)->default(0);
            $table->string('project_class_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_rewards', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['project_id']);
            $table->dropForeign(['employee_point_project_id']);
        });
        Schema::dropIfExists('employee_rewards');
    }
};
