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
        Schema::create('interactive_project_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('interactive_projects')->onDelete('cascade');
            $table->string('name');
            $table->string('sort', 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('interactive_project_boards', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::dropIfExists('interactive_project_boards');
    }
};
