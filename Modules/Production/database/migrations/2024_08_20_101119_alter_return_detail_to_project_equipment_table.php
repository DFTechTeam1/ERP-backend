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
        Schema::table('project_equipment', function (Blueprint $table) {
            $table->boolean('is_good_condition')->nullable();
            $table->string('detail_condition')->nullable();
            $table->boolean('is_returned')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_equipment', function (Blueprint $table) {
            $table->dropColumn('is_good_condition');
            $table->dropColumn('detail_condition');
            $table->dropColumn('is_returned');
        });
    }
};
