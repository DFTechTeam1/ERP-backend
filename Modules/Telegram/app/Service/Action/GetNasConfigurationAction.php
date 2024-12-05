<?php

namespace Modules\Telegram\Service\Action;

use App\Services\Telegram\TelegramService;
use Modules\Nas\Services\NasService;

class GetNasConfigurationAction {
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
        $this->service = new TelegramService();
        $this->nasService = new NasService();
    }

    public function __invoke(array $payload)
    {
        $this->setUserIdentity(payload: $payload);
        $this->setService();

        $config = $this->nasService->getConfiguration();

        $ip = $config['ip'] ? $config['ip'] : 'Belum di setting';
        $root = $config['root'] ? $config['root'] : 'Belum di setting';

        $this->service->sendTextMessage(
            chatId: $this->chatId,
            message: "IP aktif sekarang: {$ip}\nRoot folder aktif sekarang: {$root}"
        );
    }
}
