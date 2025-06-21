<?php

namespace Modules\Telegram\Jobs;

use App\Services\Telegram\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TelegramSendingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $chatId;

    private $message;

    /**
     * Create a new job instance.
     */
    public function __construct(string $chatId, mixed $message)
    {
        $this->chatId = $chatId;
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(TelegramService $service): void
    {
        if (gettype($this->message) == 'string') {
            $service->sendTextMessage($this->chatId, $this->message);
        } elseif (gettype($this->message) == 'array') {
            if (! isset($this->message['text'])) {
                $service->sendTextMessage($this->chatId, $this->message);
            } else {
                $service->sendTextMessage($this->chatId, $this->message['text'], false, $this->message['link_previews']);
            }
        }
    }
}
