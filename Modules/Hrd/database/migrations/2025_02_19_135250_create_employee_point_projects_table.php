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
        Schema::create('employee_point_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_point_id')
                ->references('id')
                ->on('employee_points')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('project_id');
            $table->integer('total_point')
                ->comment('point + additional point');
            $table->integer('additional_point');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (checkForeignKey('employee_point_projects', 'employee_point_id')) {
            Schema::table('employee_point_projects', function (Blueprint $table) {
                $table->dropForeign(['employee_point_id']);
            });
        }

        Schema::dropIfExists('employee_point_projects');
    }
};
