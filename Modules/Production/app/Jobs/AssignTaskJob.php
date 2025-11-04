<?php

namespace Modules\Production\Jobs;

use App\Repository\UserRepository;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AssignTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly array $employeeIds,
        private readonly int $taskId,
        private readonly object $userData,
        private readonly int $actorId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $task = \Modules\Production\Models\ProjectTask::selectRaw('name,project_id,id,uid')
            ->with(['project:id,name,uid'])
            ->find($this->taskId);

        $action = 'user_has_been_assigned_to_task';

        $actor = (new UserRepository)->detail(id: $this->actorId, select: 'id,employee_id', relation: [
            'employee:id,nickname'
        ]);

        foreach ($this->employeeIds as $employee) {
            $data = \Modules\Hrd\Models\Employee::selectRaw('line_id,id,uid,name,email,telegram_chat_id,nickname')
                ->find($employee);

            NotificationService::send(
                recipients: $data,
                action: $action,
                data: [
                    'parameter1' => $data->nickname,
                    'parameter2' => $task->name,
                    'parameter3' => $task->project->name,
                    'parameter4' => $actor->employee->nickname,
                ],
                channels: ['database'],
                options: [
                    'url' => config('app.frontend_url') . '/admin/production/project/' . $task->project->uid,
                    'database_type' => 'production'
                ]
            );

            // send pusher
            (new \App\Services\PusherNotification)->send(
                channel: 'my-channel-' . $this->actorId,
                event: 'new-db-notification',
                payload: [
                    'update' => true
                ],
            );
        }
    }
}
