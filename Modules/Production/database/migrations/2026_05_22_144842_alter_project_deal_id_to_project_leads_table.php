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
            $table->foreignId('project_deal_id')
                ->nullable()
                ->references('id')
                ->on('project_deals')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_leads', function (Blueprint $table) {
            $table->dropForeign(['project_deal_id']);

            $table->dropColumn('project_deal_id');
        });
    }
};
