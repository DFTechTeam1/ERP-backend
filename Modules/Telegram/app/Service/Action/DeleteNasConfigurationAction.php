<?php

namespace Modules\Telegram\Service\Action;

use App\Services\Telegram\TelegramService;
use Modules\Nas\Services\NasService;

class DeleteNasConfigurationAction
{
    private $chatId;

    private $messageId;

    private $service;

    private $nasService;

    protected function setUserIdentity(array $payload)
    {
        $this->chatId = $payload['callback_query']['message']['chat']['id'];
        $this->messageId = $payload['callback_query']['message']['message_id'];
    }

    protected function setService()
    {
        $this->service = new TelegramService;
        $this->nasService = new NasService;
    }

    public function __invoke(array $payload = [])
    {
        $this->setUserIdentity($payload);
        $this->setService();

        $this->nasService->deleteConfiguration();

        $this->service->sendTextMessage(
            chatId: $this->chatId,
            message: 'Sip. Konfigurasi sudah terhapus'
        );

        $this->service->reinit();
        $this->service->deleteMessage(
            chatId: $this->chatId,
            messageId: $this->messageId
        );
    }
}
