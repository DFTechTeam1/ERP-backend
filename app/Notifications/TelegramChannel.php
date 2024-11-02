<?php

/**
 * This is a channel to send notification via Telegram
 * Use this as centralized a nootification
 *
 * message format will be like this:
 * 1. string
 * 2. array<string>
 * 3. array<array, <string, string>>
 */

namespace App\Notifications;

use App\Services\Telegram\TelegramService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Modules\LineMessaging\Services\LineConnectionService;

class TelegramChannel {
    public function send(object $notifiable, Notification $notification)
    {
        $message = $notification->toTelegram($notifiable);

        $service = new TelegramService();

        $chatIds = $message['chatIds'];

        foreach ($chatIds as $chatId) {
            if (gettype($message['message']) == 'string') {
                \Modules\Telegram\Jobs\TelegramSendingJob::dispatch($chatId, $message['message'])->delay(now()->addSeconds(2));
            } else if (gettype($message['message']) == 'array') {
                foreach ($message['message'] as $message) {
                    if (!isset($message['text'])) {
                        if (gettype($message) == 'string') {
                            \Modules\Telegram\Jobs\TelegramSendingJob::dispatch($chatId, $message)->delay(now()->addSeconds(2));
                        } else {
                            foreach ($message as $msg) {
                                \Modules\Telegram\Jobs\TelegramSendingJob::dispatch($chatId, $msg)->delay(now()->addSeconds(2));
                            }
                        }
                    } else {
                        \Modules\Telegram\Jobs\TelegramSendingJob::dispatch($chatId, $message)->delay(now()->addSeconds(2));
                    }
                }
            }
        }
    }
}
