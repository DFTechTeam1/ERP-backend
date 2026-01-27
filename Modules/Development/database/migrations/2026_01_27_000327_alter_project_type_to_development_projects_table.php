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
        Schema::table('development_projects', function (Blueprint $table) {
            $table->enum('project_type', ['develop', 'project'])->after('description')->default('develop')->comment('Type of the project: develop or project');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('development_projects', function (Blueprint $table) {
            $table->dropColumn('project_type');
        });
    }
};
