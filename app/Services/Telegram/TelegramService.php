<?php

namespace App\Services\Telegram;

use App\Enums\Telegram\ChatStatus;
use App\Enums\Telegram\ChatType;
use App\Enums\Telegram\CommandList;
use Illuminate\Support\Facades\Http;
use Modules\Telegram\Models\TelegramChatHistory;

class TelegramService {
    private $token;

    private $url;

    private $commands;

    public function __construct()
    {
        $this->token = config('app.telegram_bot_token');

        $this->url = config('app.telegram_url');

        $this->commands = [];
    }

    protected function getUrl(string $type)
    {
        switch ($type) {
            case 'info':
                $link = '/getMe';
                break;

            case 'update':
                $link = '/getUpdates';
                break;

            case 'message':
                $link = '/sendMessage';
                break;

            case 'getMyCommands':
                $link = '/getMyCommands';
                break;

            case 'getMyName':
                $link = '/getMyName';
                break;

            case 'setMyCommand':
                $link = '/setMyCommands';
                break;

            case 'deleteAllCommands':
                $link = '/deleteMyCommands';
                break;

            case 'getWebhookInfo':
                $link = '/getWebhookInfo';
                break;

            case 'setWebhook':
                $link = '/setWebhook';
                break;

            default:
                $link = '/getMe';
                break;
        }

        $this->url = $this->url . $this->token . $link;
    }

    public function getBotInformation()
    {
        $this->getUrl('info');
        return $this->sendRequest();
    }

    public function getUpdates()
    {
        $this->getUrl('update');
        return $this->sendRequest();
    }

    public function sendTextMessage(
        string $chatId,
        string $message,
        bool $isRemoveKeyboard = false
    )
    {
        $this->getUrl('message');
        $payload = [
            'chat_id' => $chatId,
            'text' => $message,
            'remove_keyboard' => $isRemoveKeyboard,
        ];

        return $this->sendRequest('post', $payload);
    }

    public function updateConversation($chatId, $payload)
    {
        $current = TelegramChatHistory::select('id')
            ->where('chat_id', $chatId)
            ->latest()
            ->first();

        TelegramChatHistory::where('id', $current->id)
            ->update($payload);
    }

    public function storeConversation(
        string $chatId,
        string $message,
        string $chatType = '',
        string $botCommand = '',
        int $status = ChatStatus::Processing->value
    )
    {
        TelegramChatHistory::create([
            'chat_id' => $chatId,
            'message' => $message,
            'chat_type' => $chatType,
            'bot_command' => $botCommand,
            'status' => $status
        ]);
    }

    public function sendButtonMessage(string $chatId, string $message, array $keyboard)
    {
        $this->getUrl('message');
        $payload = [
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => $keyboard
        ];

        return $this->sendRequest('post', $payload);
    }

    public function getMyCommands()
    {
        $this->getUrl('getMyCommands');
        return $this->sendRequest('get');
    }

    public function addCommand(string $command, string $description = '')
    {
        $this->commands[] = [
            'command' => $command,
            'description' => $description
        ];
    }

    public function setMyCommand()
    {
        $payload = [
            'commands' => $this->commands
        ];

        $this->getUrl('setMyCommand');
        return $this->sendRequest('post', $payload);
    }

    public function deleteAllCommands()
    {
        $this->getUrl('deleteAllCommands');
        return $this->sendRequest('get');
    }

    public function getMyName()
    {
        $this->getUrl('getMyName');
        return $this->sendRequest('get');
    }

    public function getWebhookInfo()
    {
        $this->getUrl('getWebhookInfo');
        return $this->sendRequest();
    }

    public function setWebhook(string $url)
    {
        $this->getUrl('setWebhook');
        return $this->sendRequest('post', [
            'url' => $url
        ]);
    }

    public function sendRequest(string $type = 'get', array $payload = [])
    {
        $response = Http::$type($this->url, $payload);

        return $response->json();
    }
}
