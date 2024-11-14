<?php

namespace Modules\Telegram\Service\Action;

use App\Models\User;
use App\Services\Telegram\TelegramService;
use Illuminate\Support\Facades\Http;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectTask;
use Modules\Telegram\Enums\CallbackIdentity;
use Modules\Telegram\Models\TelegramTransaction;
use Modules\Telegram\Service\Webhook\Callback;

class ApproveTaskAction {
    private $service;

    private $token;

    private $chatId;

    private $messageId;

    private $tid;

    private $eid;

    private $pid;

    protected function setUserIdentity(array $payload)
    {
        $this->chatId = $payload['callback_query']['message']['chat']['id'];
        $this->messageId = $payload['callback_query']['message']['message_id'];
    }

    protected function setProjectParams(array $payload)
    {
        parse_str($payload['callback_query']['data'], $queries);

        $this->tid = $queries['tid'];
        $this->eid = $queries['eid'];
        $this->pid = $queries['pid'];
    }

    protected function setService()
    {
        $this->service = new TelegramService();
    }

    protected function setAuth()
    {
        if (!$this->token) {
            $user = User::where('employee_id', $this->eid)->first();
            $role = $user->getRoleNames()[0];
            $roles = $user->roles;
            $permissions = count($user->getAllPermissions()) > 0 ? $user->getAllPermissions()->pluck('name')->toArray() : [];

            $token = $user->createToken($role, $permissions, now()->addHours(2));

            $this->token = $token->plainTextToken;
        }
    }

    public function __invoke(array $payload)
    {
        $this->setUserIdentity($payload);
        $this->setProjectParams($payload);
        $this->setService();

        // register transaction if needed
        $check = TelegramTransaction::select('id', 'status')
            ->where('chat_id', $this->chatId)
            ->where('message_id', $this->messageId)
            ->where('identity', CallbackIdentity::ApproveTask->value)
            ->first();
        if (($check) && ($check->status)) {
            $this->service->sendTextMessage($this->chatId, 'Wuusshhh, sabar ya bos! masih di proses');
            return;
        } else if (($check) && (!$check->status)) {
            // stop the process
            return;
        }

        TelegramTransaction::create([
            'chat_id' => $this->chatId,
            'message_id' => $this->messageId,
            'identity' => CallbackIdentity::ApproveTask->value,
        ]);

        // login as user
        $this->setAuth();

        // get project and task uid
        $taskData = ProjectTask::selectRaw('id,project_id,uid')
            ->with('project:uid,id')
            ->where('id', $this->tid)
            ->first();

        $response = Http::withToken($this->token)
            ->get(config('app.url') . "/api/production/project/{$taskData->project->uid}/task/{$taskData->uid}/approve");

        if ($response->successful()) {
            $this->service->sendTextMessage($this->chatId, "Yeahhh tugas sudah bisa kamu kerjakan sekarang 🥳", true);

            // remove inline keyboard
            $this->service->reinit();
            $this->service->deleteMessage($this->chatId, $this->messageId);

            // update status
            TelegramTransaction::where('chat_id', $this->chatId)
                ->where('message_id', $this->messageId)
                ->where('identity', CallbackIdentity::ApproveTask->value)
                ->update([
                    'status' => 0
                ]);
        } else {
            $this->service->sendTextMessage($this->chatId, 'Wahhh sepertinya aku belum bisa membantumu untuk tugas ini');
        }
    }
}
