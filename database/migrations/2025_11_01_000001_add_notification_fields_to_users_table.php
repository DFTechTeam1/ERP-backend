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
        Schema::table('users', function (Blueprint $table) {
            // Telegram integration
            $table->string('telegram_chat_id')->nullable()->after('email');
            $table->index('telegram_chat_id');
            
            // Slack integration
            $table->string('slack_webhook_url')->nullable()->after('telegram_chat_id');
            $table->string('slack_channel')->nullable()->after('slack_webhook_url');
            
            // User notification preferences
            // Format: {'action_name': ['email', 'database', 'telegram'], ...}
            $table->json('notification_preferences')->nullable()->after('slack_channel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['telegram_chat_id']);
            $table->dropColumn([
                'telegram_chat_id',
                'slack_webhook_url',
                'slack_channel',
                'notification_preferences',
            ]);
        });
    }
};
