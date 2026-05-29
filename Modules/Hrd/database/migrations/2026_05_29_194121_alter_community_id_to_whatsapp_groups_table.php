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
            $table->string('community_id')
                ->nullable()
                ->after('id');

            // Foreign key constraint nullable
            $table->foreign('community_id')->references('community_id')->on('whatsapp_communities')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_groups', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['community_id']);
            
            $table->dropColumn('community_id');
        });
    }
};
