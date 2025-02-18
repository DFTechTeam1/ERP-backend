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
        Schema::table('telegram_chat_histories', function (Blueprint $table) {
            $table->string('chat_type')->nullable();
            $table->string('bot_command')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_chat_histories', function (Blueprint $table) {
            $table->dropColumn('chat_type');
            $table->dropColumn('bot_command');
        });
    }
};
