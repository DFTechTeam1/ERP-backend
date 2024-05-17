<?php

namespace Modules\LineMessaging\Services;

use Illuminate\Support\Facades\Http;

class LineConnectionService {
    private $url;
    private $token;

    public function __construct()
    {
        $this->url = 'https://api.line.me/v2/bot';
        $this->token = config('linemessaging.line_token');
    }

    public function sendMessage(array $message, string $lineId)
    {
        $response = Http::withToken($this->token)
            ->post($this->url . '/message/push', [
                'to' => $lineId,
                'messages' => $message
            ]);
        
        $response = json_decode($response->body(), true);

        return $response;
    }

    public function webhook(array $data)
    {
        if (isset($data['events'])) {
            $message = $data['events'][0]['message'];

            if ($message['text'] == '/register-line-addon') {
                $lineId = $message['id'];

                \Modules\Company\Models\Setting::create([
                    'code' => 'addon',
                    'key' => 'lineId',
                    'value' => $lineId,
                ]);
            }
        }
    }
}