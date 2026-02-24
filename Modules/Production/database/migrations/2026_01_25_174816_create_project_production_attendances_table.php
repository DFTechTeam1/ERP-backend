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
        Schema::create('project_production_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')
                ->onDelete('cascade');
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_production_attendances', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['employee_id']);
        });

        Schema::dropIfExists('project_production_attendances');
    }
};
