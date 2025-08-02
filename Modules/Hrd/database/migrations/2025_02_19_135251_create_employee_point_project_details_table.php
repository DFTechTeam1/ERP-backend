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
        Schema::create('employee_point_project_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_id')
                ->references('id')
                ->on('employee_point_projects')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('task_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (checkForeignKey('employee_point_project_details', 'point_id')) {
            Schema::table('employee_point_project_details', function (Blueprint $table) {
                $table->dropForeign(['point_id']);
            });
        }

        Schema::dropIfExists('employee_point_project_details');
    }
};
