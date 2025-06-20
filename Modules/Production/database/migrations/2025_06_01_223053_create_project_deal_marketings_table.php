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
        Schema::create('project_deal_marketings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_deal_id')
                ->references('id')
                ->on('project_deals');
            $table->foreignId('employee_id')
                ->references('id')
                ->on('employees');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_deal_marketings', function (Blueprint $table) {
            $table->dropForeign(['project_deal_id']);
            $table->dropForeign(['employee_id']);
        });
        Schema::dropIfExists('project_deal_marketings');
    }
};
