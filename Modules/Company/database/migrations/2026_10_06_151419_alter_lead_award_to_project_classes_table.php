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
        Schema::table('project_class_pm_tiers', function (Blueprint $table) {
            $table->decimal('lead_reward', 24, 2)->default(0);
            $table->decimal('support_reward', 24, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_class_pm_tiers', function (Blueprint $table) {
            $table->dropColumn('lead_reward');
            $table->dropColumn('support_reward');
        });
    }
};
