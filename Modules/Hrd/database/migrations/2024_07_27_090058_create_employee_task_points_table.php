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
        Schema::create('employee_task_points', function (Blueprint $table) {
            $table->id();
            $table->integer('employee_id');
            $table->float('point');
            $table->float('additional_point')->default(0);
            $table->float('total_point')->default(0);
            $table->float('total_task')->default(0);
            $table->integer('project_id');
            $table->integer('created_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_task_points');
    }
};
