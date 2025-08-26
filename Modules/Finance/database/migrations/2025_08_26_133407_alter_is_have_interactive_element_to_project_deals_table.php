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
            $table->boolean('is_have_interactive_element')->default(false)->after('cancel_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        SChema::table('project_deals', function (Blueprint $table) {
            $table->dropColumn('is_have_interactive_element');
        });
    }
};
