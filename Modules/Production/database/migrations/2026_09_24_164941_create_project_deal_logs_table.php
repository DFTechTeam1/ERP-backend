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
        Schema::create('project_deal_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 150);
            $table->foreignId('project_deal_id')
                ->constrained('project_deals')
                ->cascadeOnDelete();
            $table->string('description')->nullable();
            $table->string('actor_name')->comment('snapshot for actor name');
            $table->string('actor_role')->comment('snapshot for actor role');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_deal_logs', function (Blueprint $table) {
            $table->dropForeign(['project_deal_id']);
        });
        Schema::dropIfExists('project_deal_logs');
    }
};
