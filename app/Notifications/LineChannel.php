<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Modules\LineMessaging\Services\LineConnectionService;

class LineChannel {
    public function send(object $notifiable, Notification $notification)
    {
        $message = $notification->toLine($notifiable);

        $service = new LineConnectionService();

        $line_ids = $message['line_ids'];
        foreach ($line_ids as $line_id) {
            $sendLine = $service->sendMessage($message['messages'], $line_id);

            logging('result send line message', [$sendLine]);
        }

        logging('message line: ', $message);
    }
}