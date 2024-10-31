<?php

namespace App\Notifications;

use App\Services\Telegram\TelegramService;
use Illuminate\Notifications\Notification;
use Modules\LineMessaging\Services\LineConnectionService;

class TelegramChannel {
    public function send(object $notifiable, Notification $notification)
    {
        $message = $notification->toTelegram($notifiable);

        $service = new TelegramService();

        $chatIds = $message['chatIds'];

        foreach ($chatIds as $chatId) {
            if ($chatId) {
                if (gettype($message['message']) == 'string') {
                    $sendTelegram = $service->sendTextMessage($chatId, $message['message']);
                } else if (gettype($message['message']) == 'array') {
                    foreach ($message['message'] as $messageData) {
                        $service->sendTextMessage($chatId, $messageData);
                    }
                }

                logging('result send line message', [$sendTelegram]);
            }
        }
    }
}
