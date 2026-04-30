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
        Schema::table('project_leads', function (Blueprint $table) {
            $table->longText('cell_options')->nullable()->after('is_final')->comment('This column will be used to store the options for each cell in the project lead table. Format will be {cell_name: {option1: value1, option2: value2}}');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_leads', function (Blueprint $table) {
            $table->dropColumn('cell_options');
        });
    }
};
