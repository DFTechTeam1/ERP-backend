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
        Schema::table('project_classes', function (Blueprint $table) {
            $table->decimal('pm_reward', 24, 2)->default(0)->after('reward');
            $table->decimal('vj_reward', 24, 2)->default(0)->after('pm_reward');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_classes', function (Blueprint $table) {
            $table->dropColumn(['pm_reward', 'vj_reward']);
        });
    }
};
