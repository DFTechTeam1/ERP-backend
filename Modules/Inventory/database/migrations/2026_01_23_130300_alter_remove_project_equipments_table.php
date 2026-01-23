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
        if (Schema::hasIndex('project_equipment', 'project_equipment_project_id_foreign')) {
            Schema::table('project_equipment', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
            });
        }

        Schema::dropIfExists('project_equipment');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('project_equipment', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
};
