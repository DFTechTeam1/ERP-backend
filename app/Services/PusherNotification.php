<?php

namespace App\Services;

use Pusher\Pusher;

class PusherNotification
{
    private $pusher;

    public function __construct()
    {
        $this->pusher = new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            [
                'cluster' => 'ap1',
            ],
        );
    }

    public function send(string $channel, string $event, array $payload, bool $compressedValue = false)
    {
        $payload = $compressedValue ? json_encode($payload) : $payload;
        $this->pusher->trigger($channel, $event, $payload);
    }
}
