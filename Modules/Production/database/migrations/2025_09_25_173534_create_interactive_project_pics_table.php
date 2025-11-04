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
        Schema::create('interactive_project_pics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intr_project_id')->constrained('interactive_projects')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('interactive_project_pics', function (Blueprint $table) {
            $table->dropForeign(['intr_project_id']);
            $table->dropForeign(['employee_id']);
        });
        Schema::dropIfExists('interactive_project_pics');
    }
};
