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
        Schema::create('project_vjs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();
            $table->foreignId('employee_id')
                ->references('id')
                ->on('employees')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->integer('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_vjs');
    }
};
