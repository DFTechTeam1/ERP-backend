<?php

namespace Modules\Telegram\Service\Action;

use App\Services\Telegram\TelegramService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Modules\Telegram\Enums\TelegramSessionKey;
use Modules\Telegram\Models\TelegramSession;

class SetNasIpAction {
    private $chatId;
    private $messageId;
    private $service;

    protected function setUserIdentity(array $payload)
    {
        $this->chatId = $payload['callback_query']['message']['chat']['id'];
        $this->messageId = $payload['callback_query']['message']['message_id'];
    }

    protected function setService()
    {
        $this->service = new TelegramService();
    }

    public function __invoke(array $payload)
    {
        $this->setUserIdentity(payload: $payload);
        $this->setService();

        // set session
        putTelegramSession(chatId: $this->chatId, value: TelegramSessionKey::WaitingNasIp->value);

        $this->service->sendTextMessage(
            chatId: $this->chatId,
            message: "Silahkan ketik IP nas. Hanya ketik IP tanpa ada protokol 'http' atau 'https'.\nContoh penulisan yang benar seperti berikut ini:\n\n192.168.99.250:5000"
        );
    }
}
