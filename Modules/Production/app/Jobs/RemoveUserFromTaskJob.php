<?php

namespace Modules\Production\Jobs;

use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RemoveUserFromTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  array<string>  $employeeUids
     */
    public function __construct(
        private readonly array $employeeUids,
        private readonly int $taskId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $task = \Modules\Production\Models\ProjectTask::selectRaw('id,project_id,name')
            ->with(['project:id,name,uid'])
            ->find($this->taskId);

        $action = 'user_has_been_removed_from_task';

        foreach ($this->employeeUids as $employeeUid) {
            $employeeId = getIdFromUid($employeeUid, new \Modules\Hrd\Models\Employee);
            $employee = \Modules\Hrd\Models\Employee::find($employeeId);

            NotificationService::send(
                recipients: $employee,
                action: $action,
                data: [
                    'parameter1' => $employee->nickname,
                    'parameter2' => $task->name,
                    'parameter3' => $task->project->name,
                ],
                channels: ['database'],
                options: [
                    'url' => config('app.frontend_url') . '/admin/production/project/' . $task->project->uid,
                    'database_type' => 'production'
                ]
            );

            // send pusher
            (new \App\Services\PusherNotification)->send(
                channel: 'my-channel-' . $employee->user_id,
                event: 'new-db-notification',
                payload: [
                    'update' => true
                ],
            );
        }
    }
}
