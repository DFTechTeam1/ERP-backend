<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService {
    private bool $status;

    private $token;

    private $additionalParameters;

    public function __construct()
    {
        $this->status = true;
        $this->token = '3b70M8t9';

        Log::debug('whatsapp updated service status: ', [$this->status]);
    }

    public function checkConnection()
    {
        return $this->getAllTemplates();
    }

    protected function getAllTemplates()
    {
        $response = Http::withHeaders([
            'token' => $this->token
        ])->get('https://smartchatapi.com/w4b_salasar_ecommerce/Api/get_view_template');

        return $response->json();
    }

    protected function send(array $payload)
    {
        Log::debug('payload send template: ', $payload);

        $response = Http::withHeaders([
            'token' => $this->token
        ])->post('https://smartchatapi.com/w4b_salasar_ecommerce/Api/send_template_message', $payload);

        Log::debug('response send message: ', [$response->json()]);

        return $response->json();
    }

    public function sendTemplateMessage(string $template, array $additional, array $numbers = [])
    {
        if ($this->status && $this->token) {
            // get template
            $payload = [];
            foreach ($numbers as $key => $number) {
                $item = [
                    'sender_whatsapp_number' => $number,
                    'template_name' => $template,
                    'broadcast_name' => 'Send Whatsapp',
                ];

                $payload = array_merge($additional, $item);

                $this->send($payload);
            }
        }
    }
}
