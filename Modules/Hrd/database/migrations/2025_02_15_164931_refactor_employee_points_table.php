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
        if (checkForeignKey('employee_points', 'employee_id')) {
            Schema::table('employee_points', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
            });
        }

        Schema::dropIfExists('employee_points');

        Schema::create('employee_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->references('id')
                ->on('employees');
            $table->integer('total_point')->default(1);
            $table->integer('additional_point')->default(1);
            $table->enum('type', ['entertainment', 'production']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (checkForeignKey('employee_points', 'employee_id')) {
            Schema::table('employee_points', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
            });
        }

        Schema::dropIfExists('employee_points');

        Schema::create('employee_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->references('id')
                ->on('employees')
                ->cascadeOnDelete();
            $table->integer('point')->default(0);
            $table->integer('additional_point')->default(0);
            $table->bigInteger('project_id');
            $table->enum('task_type', ['production', 'entertainment']);
            $table->unsignedBigInteger('task_id');
            $table->timestamps();
        });
    }
};
