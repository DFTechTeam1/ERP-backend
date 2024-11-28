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
        Schema::create('nas_folder_creations', function (Blueprint $table) {
            $table->id();
            $table->string('project_name', 255);
            $table->foreignId('project_id')
                ->references('id')->on('projects')
                ->cascadeOnDelete();
            $table->json('folder_path');
            $table->boolean('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nas_folder_creations', function (Blueprint $table) {
            $table->dropForeign('nas_folder_creations_project_id_foreign');
        });
        Schema::dropIfExists('nas_folder_creations');
    }
};
