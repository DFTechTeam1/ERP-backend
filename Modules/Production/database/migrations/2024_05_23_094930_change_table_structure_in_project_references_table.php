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
        Schema::table('project_references', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('type', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_references', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->dropColumn('type', 100);
        });
    }
};
