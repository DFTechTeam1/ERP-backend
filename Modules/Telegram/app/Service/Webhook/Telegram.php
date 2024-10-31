<?php

namespace Modules\Telegram\Service\Webhook;

use App\Enums\Telegram\ChatStatus;
use App\Enums\Telegram\ChatType;
use App\Enums\Telegram\CommandList;
use App\Services\Telegram\TelegramService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Hrd\Models\Employee;
use Modules\Telegram\Models\TelegramChatHistory;

class Telegram {
    private $senderId;

    private $senderName;

    private $chatId;

    private $message;

    private $service;

    public function __construct()
    {
        $this->service  = new TelegramService();
    }

    public function categorize(array $payload)
    {
        if (
            (isset($payload['message'])) &&
            (isset($payload['message']['message_id']))
        ) {
            $this->senderId = $payload['message']['from']['id'];
            $this->senderName = $payload['message']['from']['username'];
            $this->chatId = $payload['message']['chat']['id'];

            $text = $payload['message']['text'];
            $this->message = $text;

            if (isset($payload['message']['entities'])) {
                if ($payload['message']['entities'][0]['type'] == 'bot_command') {
                    $method = "handle" . ucfirst(ltrim($text, '/'));
                    Log::debug('command method', [$method]);
                    if (method_exists($this, $method)) {
                        return $this->handleConnection();
                    } {
                        $this->service->sendTextMessage($this->chatId, 'Wah saya belum bisa memproses pesan kamu. Ulangi lagi yaa');
                    }
                }
            } else {
                $this->handleFreeText();
            }
        }
    }

    public function handleFreeText()
    {
        $current = TelegramChatHistory::selectRaw('id,chat_id,from_customer,bot_command,message,chat_type,is_closed')
            ->where('chat_id', $this->chatId)
            ->orderBy('id', 'desc')
            ->first();

        Log::debug('current', $current->toArray());

        if ($current->is_closed) {
            return $this->service->sendTextMessage($this->chatId, 'Wah saya belum memahami apa yang kamu bicarakan. Coba mulai dari awal dengan command yang ada di menu ya 🙂');
        }

        // Only process message that came after bot message
        if (!$current->from_customer) {
            if ($current->chat_type == ChatType::FreeText->value) {
                if ($current->bot_command == CommandList::Connection->value) {
                    return $this->handleConnectionReply($current);
                }
            }
        }
    }

    /**
     * In this function we assume that message will have 'Employee ID Format' something like DF001 DF00.....
     *
     * @return void
     */
    protected function handleConnectionReply(object $current)
    {
        // check employee format
        $text = strtolower(trim(str_replace(' ', '', $this->message)));
        Log::debug('check lagi', [$text]);
        if (!Str::startsWith($text, 'df')) {
            return $this->service->sendTextMessage($this->chatId, 'Format employee ID kamu salah. Masukan lagi dengan benar');
        } else {
            // check data
            $employee = Employee::selectRaw('id,telegram_chat_id')
                ->where('employee_id', $text)
                ->first();
            Log::debug('current employee', $employee->toArray());
            if (!$employee) {
                return $this->service->sendTextMessage($this->chatId, 'Karyawan tidak ditemukan nih. Sepertin employee ID yang kamu masukan salah');
            }

            if ($employee->telegram_chat_id) {
                // closed
                TelegramChatHistory::where('id', $current->id)
                    ->update(['is_closed' => 1]);
                return $this->service->sendTextMessage($this->chatId, 'Akun mu sudah terhubung pada aplikasi');
            }

            Employee::where('employee_id', $text)
                ->update(['telegram_chat_id' => $this->chatId]);

            TelegramChatHistory::where('id', $current->id)
                ->update(['is_closed' => 1]);

            return $this->service->sendTextMessage($this->chatId, 'Selamat telegram kamu sudah terhubung dengan aplikasi. Selamat bekerja 😇');
        }
    }

    protected function resetVariable()
    {
        $this->senderId = null;
        $this->senderName = null;
        $this->message = '';
    }

    public function handleConnection(): void
    {
        TelegramChatHistory::create([
            'chat_id' => $this->chatId,
            'message' => CommandList::Connection->value,
            'status' => ChatStatus::Sent->value,
            'chat_type' => ChatType::BotCommand->value,
            'bot_command' => CommandList::Connection->value,
            'from_customer' => true
        ]);

        $replyMessage = 'Halo '. $this->senderName .' Beritahu saya employee ID kamu';

        $newMessage = TelegramChatHistory::create([
            'chat_id' => $this->chatId,
            'message' => $replyMessage,
            'status' => ChatStatus::Processing->value,
            'chat_type' => ChatType::FreeText->value,
            'bot_command' => CommandList::Connection->value,
            'from_customer' => false,
        ]);

        $send = $this->service->sendTextMessage(
            $this->chatId,
            'Halo '. $this->senderName .' Beritahu saya employee ID kamu',
            true
        );

        TelegramChatHistory::where('id', $newMessage->id)
            ->update([
                'status' => $send['ok'] ? ChatStatus::Sent->value: ChatStatus::Failed->value
            ]);

        $this->resetVariable();
    }
}
