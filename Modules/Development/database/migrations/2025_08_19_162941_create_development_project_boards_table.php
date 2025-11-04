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
        Schema::create('development_project_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_project_id')
                ->constrained('development_projects')
                ->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('development_project_boards', function (Blueprint $table) {
            $table->dropForeign(['development_project_id']);
        });

        Schema::dropIfExists('development_project_boards');
    }
};
