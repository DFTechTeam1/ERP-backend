<?php

/**
 * This is dedicated class to handle all callback
 * Callback will have some key to identity the topic
 * All Identity can be check on Modules/Telegram/app/Enums/CallbackIdentity.php
 *
 * The callback data format should follow rules on each action
 */

namespace Modules\Telegram\Service\Webhook;

use App\Models\User;
use App\Services\Telegram\InlineKeyboard;
use App\Services\Telegram\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Hrd\Models\Employee;
use Modules\Telegram\Service\Action\ApproveTaskAction;
use Modules\Telegram\Enums\CallbackIdentity;
use Modules\Telegram\Service\Action\CheckProofOfWorkAction;
use Modules\Telegram\Service\Action\DeleteNasConfigurationAction;
use Modules\Telegram\Service\Action\GetNasConfigurationAction;
use Modules\Telegram\Service\Action\MarkTaskAsCompleteAction;
use Modules\Telegram\Service\Action\MyProjectAction;
use Modules\Telegram\Service\Action\MyTaskAction;
use Modules\Telegram\Service\Action\SetNasIpAction;
use Modules\Telegram\Service\Action\SetNasRootAction;

class Callback {
    private $value;

    private $identity;

    private $service;

    private $chatId;

    private $messageId;

    private $additional;

    private $employee;

    private $user;

    protected function breakTheValue(string $data)
    {
        [$this->value, $this->identity] = explode('&identity=', $data);

        // check additional data
        if (Str::contains($this->identity, '&a=')) {
            // get the additional
            [$this->identity,$this->additional] = explode('&a=', $this->identity);
        }

        Log::debug('additional', [$this->additional]);
        Log::debug('identity', [$this->identity]);
    }

    protected function setService()
    {
        $this->service = new TelegramService();
    }

    protected function getChatId(array $payload)
    {
        $this->chatId = $payload['callback_query']['message']['chat']['id'];
    }

    protected function getMessageId(array $payload)
    {
        $this->messageId = $payload['callback_query']['message']['message_id'];
    }

    protected function getUserData()
    {
        $this->employee = Employee::selectRaw('id,name,nickname')
            ->where('telegram_chat_id', $this->chatId)
            ->first();

        $this->user = User::where('employee_id', $this->employee->id)->first();
    }

    /**
     * Sometime payload came from FREETEXT, instead of CALLBACK QUERY
     * @param array $payload
     * @return void
     */
    public function handle(array $payload)
    {
        Log::debug('payload callback query', $payload);

        if (isset($payload['callback_query'])) {
            $data = $payload['callback_query']['data'];
            parse_str($data, $queries);

            if ($queries['idt'] == CallbackIdentity::MyProject->value) {
                $projectAction = new MyProjectAction();
                $projectAction($payload);
            } else if ($queries['idt'] == CallbackIdentity::MyTask->value) {
                $taskAction = new MyTaskAction();
                $taskAction->handle($payload);
            } else if ($queries['idt'] == CallbackIdentity::ApproveTask->value) {
                $approveAction = new ApproveTaskAction();
                $approveAction($payload);
            } else if ($queries['idt'] == CallbackIdentity::CheckProofOfWork->value) {
                $checkAction = new CheckProofOfWorkAction();
                $checkAction($payload);
            } else if ($queries['idt'] == CallbackIdentity::MarkTaskAsComplete->value) {
                $completeAction = new MarkTaskAsCompleteAction();
                $completeAction($payload);
            } else if ($queries['idt'] == CallbackIdentity::SetActiveRoot->value) {
                $rootAction = new SetNasRootAction();
                $rootAction($payload);
            } else if ($queries['idt'] == CallbackIdentity::SetActiveIP->value) {
                $ipAction = new SetNasIPAction();
                $ipAction($payload);
            } else if ($queries['idt'] == CallbackIdentity::GetNasConfiguration->value) {
                $nasConfig = new GetNasConfigurationAction();
                $nasConfig($payload);
            } else if ($queries['idt'] == CallbackIdentity::DeleteNasConfiguration->value) {
                $deleteConfigAction = new DeleteNasConfigurationAction();
                $deleteConfigAction($payload);
            }
        } else if (isset($payload['message'])) {

        }
//
//        $this->breakTheValue($data);
//        $this->getChatId($payload);
//        $this->getMessageId($payload);
//        $this->getUserData();
//        $this->setService();
//
//        if ($this->identity == CallbackIdentity::MyProject->value) {
//            $additional = [];
//            if ($this->additional) {
//                $additional = json_decode($this->additional, true);
//            }
//
//            $projectAction = new MyProjectAction();
//            $projectAction($payload, $this->chatId, $this->messageId, $additional);
//        }
    }
}
