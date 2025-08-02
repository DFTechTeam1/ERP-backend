<?php

namespace Modules\Telegram\Service\Action;

use App\Services\Telegram\TelegramService;
use Illuminate\Support\Facades\Session;
use Modules\Telegram\Enums\TelegramSessionKey;

class SetNasRootAction
{
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
        $this->service = new TelegramService;
    }

    public function __invoke(array $payload)
    {
        $this->setUserIdentity(payload: $payload);
        $this->setService();

        // set session
        putTelegramSession(chatId: $this->chatId, value: TelegramSessionKey::WaitingRootFolderName->value);

        $this->service->sendTextMessage(
            chatId: $this->chatId,
            message: "Silahkan ketik nama root folder. Jangan sertakan '/' pada root.\nContoh penulisan yang benar seperti berikut ini:\n\nQueue_Job_8"
        );
    }
}
