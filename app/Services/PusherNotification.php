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
                'cluster' => config('broadcasting.connections.pusher.options.cluster', 'ap1'),
                'useTLS' => true,
            ],
        );
    }

    public function send(string $channel, string $event, array $payload, bool $compressedValue = false)
    {
        $payload = $compressedValue ? json_encode($payload) : $payload;
        $this->pusher->trigger($channel, $event, $payload);
    }

    /**
     * Sign a private-channel subscription. Returns the JSON auth string
     * ({"auth":"<key>:<signature>"}) expected by the pusher-js auth endpoint.
     */
    public function authorize(string $channel, string $socketId): string
    {
        return $this->pusher->authorizeChannel($channel, $socketId);
    }
}
