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
        Schema::table('project_deals', function (Blueprint $table) {
            $table->boolean('is_fully_paid')->default(false)
                ->comment('Define this project has been paid off or not');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_deals', function (Blueprint $table) {
            $table->dropColumn('is_fully_paid');
        });
    }
};
