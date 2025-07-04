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
        Schema::disableForeignKeyConstraints();

        if (checkForeignKey('employee_points', 'employee_id')) {
            Schema::table('employee_points', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
            });
        }

        if (Schema::hasIndex('employee_points', 'employee_id')) {
            Schema::table('employee_points', function (Blueprint $table) {
                $table->dropIndex(['employee_id']);
            });
        }

        Schema::dropIfExists('employee_points');

        Schema::create('employee_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->references('id')
                ->on('employees');
            $table->integer('total_point')
                ->comment('This is a global total point for each employee');
            $table->enum('type', ['production', 'entertainment']);
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

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

        Schema::enableForeignKeyConstraints();
    }
};
