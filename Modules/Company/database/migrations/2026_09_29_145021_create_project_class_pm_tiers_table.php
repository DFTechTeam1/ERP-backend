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
        Schema::create('project_class_pm_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_class_id')
                ->constrained('project_classes')
                ->cascadeOnDelete();
            $table->tinyInteger('pm_count');
            $table->decimal('pm_reward', 24, 2)->default(0);
            $table->decimal('production_reward', 24, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_class_pm_tiers', function (Blueprint $table) {
            $table->dropForeign(['project_class_id']);
        });
        Schema::dropIfExists('project_class_pm_tiers');
    }
};
