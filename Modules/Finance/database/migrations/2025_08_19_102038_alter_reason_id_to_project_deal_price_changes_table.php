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
        Schema::table('project_deal_price_changes', function (Blueprint $table) {
            $table->bigInteger('reason_id')->unsigned()->nullable()->after('new_price');
            $table->string('custom_reason')->nullable()->default(null)->after('reason_id');
            $table->dropColumn('reason'); // Remove the old 'reason' column
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_deal_price_changes', function (Blueprint $table) {
            $table->dropColumn(['reason_id', 'custom_reason']);
            $table->string('reason')->after('new_price'); // Re-add the old 'reason' column
        });
    }
};
