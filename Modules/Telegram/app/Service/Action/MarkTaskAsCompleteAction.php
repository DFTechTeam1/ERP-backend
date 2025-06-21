<?php

namespace Modules\Telegram\Service\Action;

use App\Services\Telegram\TelegramService;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Services\ProjectService;
use Modules\Telegram\Enums\CallbackIdentity;

class MarkTaskAsCompleteAction
{
    private $chatId;

    private $messageId;

    private $service;

    private $projectService;

    private $employee;

    protected function setService()
    {
        $this->service = new TelegramService;

        $this->projectService = new ProjectService;
    }

    protected function setUserIdentity(array $payload)
    {
        $this->chatId = $payload['callback_query']['message']['chat']['id'];
        $this->messageId = $payload['callback_query']['message']['message_id'];

        $this->employee = Employee::where('telegram_chat_id', $this->chatId)->first();

    }

    public function __invoke(array $payload)
    {
        $this->setUserIdentity($payload);
        $this->setService();

        $data = $payload['callback_query']['data'];
        [,$taskId] = explode('idt='.CallbackIdentity::MarkTaskAsComplete->value.'&tid=', $data);

        if ($taskId) {
            $task = ProjectTask::selectRaw('project_id,id,uid')
                ->with(['project:id,uid'])
                ->find($taskId);

            $complete = $this->projectService->markAsCompleted(
                projectUid: $task->project->uid,
                taskUid: $task->uid,
                employee: $this->employee,
            );
            if (! $complete['error']) {
                $send = $this->service->sendTextMessage(
                    chatId: $this->chatId,
                    message: 'Yuhuuuu tugas ini sudah complete 👍',
                    isRemoveKeyboard: true,
                );

                if ($send) {
                    $this->service->reinit();

                    // delete inline keyboard
                    $this->service->deleteMessage(chatId: $this->chatId, messageId: $this->messageId);
                }
            }
        }
    }
}
