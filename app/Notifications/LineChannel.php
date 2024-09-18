<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Modules\LineMessaging\Services\LineConnectionService;

class LineChannel {
    public function send(object $notifiable, Notification $notification)
    {
        $message = $notification->toLine($notifiable);

        $service = new LineConnectionService();

        $lineIds = $message['line_ids'];

        foreach ($lineIds as $lineId) {
            if ($lineId) {
                $sendLine = $service->sendMessage($message['messages'], $lineId);

                logging('result send line message', [$sendLine]);
            }
        }

        logging('message line: ', $message);
    }
}