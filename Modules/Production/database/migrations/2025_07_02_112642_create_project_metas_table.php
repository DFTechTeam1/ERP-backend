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
        Schema::create('project_metas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();
            $table->json('teams_meta')
                ->nullable()
                ->comment('Structure will be like: [{"pic_id": number, "teams": [number,number], "team_transfer": [number,number]}]');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_metas', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });
        
        Schema::dropIfExists('project_metas');
    }
};
