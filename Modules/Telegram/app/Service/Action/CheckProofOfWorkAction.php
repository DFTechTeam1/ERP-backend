<?php

namespace Modules\Telegram\Service\Action;

use App\Services\Telegram\TelegramService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Modules\Production\Models\ProjectTask;
use Modules\Telegram\Enums\CallbackIdentity;

class CheckProofOfWorkAction
{
    private $chatId;

    private $messageId;

    protected function setUserIdentity(array $payload)
    {
        $this->chatId = $payload['callback_query']['message']['chat']['id'];
        $this->messageId = $payload['callback_query']['message']['message_id'];
    }

    public function __invoke(array $payload)
    {
        $this->setUserIdentity($payload);

        $data = $payload['callback_query']['data'];
        [,$taskId] = explode('idt='.CallbackIdentity::CheckProofOfWork->value.'&tid=', $data);

        if ($taskId) {
            $task = ProjectTask::find($taskId);
            Log::debug('task', $task->toArray());
            if (count($task->proofOfWorks) > 0) {
                $proofOfWorks = collect((object) $task->proofOfWorks)->map(function ($item) {
                    return [
                        'type' => 'photo',
                        'media' => $item->images[0],
                        'caption' => 'Preview',
                    ];
                })->toArray();

                // handle local devleopment
                if (App::environment('local') && config('app.url') != config('app.staging_url')) {
                    $proofOfWorks = [
                        [
                            'type' => 'photo',
                            'media' => 'https://backend.dfactory.pro/images/user.png',
                            'caption' => 'preview',
                        ],
                    ];
                }

                $service = new TelegramService;
                $send = $service->sendPhoto($this->chatId, 'Preview', $proofOfWorks);
                $service->reinit();
                if ($send) {
                    $delete = $service->deleteMessage(chatId: $this->chatId, messageId: $this->messageId);

                    if ($delete) {
                        $service->reinit();

                        // send inline keyboard to approve
                        $service->sendButtonMessage($this->chatId, 'Selesaikan pekerjaan?', [
                            'inline_keyboard' => [
                                [
                                    ['text' => 'Selesaikan Saja', 'callback_data' => 'idt='.\Modules\Telegram\Enums\CallbackIdentity::MarkTaskAsComplete->value.'&tid='.$taskId],
                                ],
                            ],
                        ]);
                    }
                }
            }
        }
    }
}
