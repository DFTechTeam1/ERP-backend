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
        Schema::create('development_project_pics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_project_id')
                ->constrained('development_projects')
                ->onDelete('cascade');
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // delete foreign
        Schema::table('development_project_pics', function (Blueprint $table) {
            $table->dropForeign(['development_project_id']);
            $table->dropForeign(['employee_id']);
        });

        Schema::dropIfExists('development_project_pics');
    }
};
