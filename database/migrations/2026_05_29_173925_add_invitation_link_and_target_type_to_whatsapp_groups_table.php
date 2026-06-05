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
        Schema::table('whatsapp_groups', function (Blueprint $table) {
            $table->string('invitation_link')->nullable()->after('group_name');
            $table->string('target_type')->default('team')->after('invitation_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_groups', function (Blueprint $table) {
            $table->dropColumn(['invitation_link', 'target_type']);
        });
    }
};
