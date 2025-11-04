<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TaskIsCompleteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $employeeIds;

    private $taskId;

    /**
     * Create a new job instance.
     */
    public function __construct(array $employeeIds, int $taskId)
    {
        $this->employeeIds = $employeeIds;

        $this->taskId = $taskId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $task = \Modules\Production\Models\ProjectTask::find($this->taskId);

        foreach ($this->employeeIds as $employeeId) {
            $employee = \Modules\Hrd\Models\Employee::find($employeeId);

            // \App\Services\NotificationService::send(
            //     recipients: $manager->employee,
            //     action: 'task_has_been_hold_by_user',
            //     data: [
            //         'parameter1' => $manager->employee->nickname,
            //         'parameter2' => $task->name,
            //         'parameter3' => $task->project->name,
            //         'parameter4' => $taskPic->nickname,
            //     ],
            //     channels: ['database'],
            //     options: [
            //         'url' => '/admin/production/project/' . $task->project->uid,
            //         'database_type' => 'production'
            //     ]
            // );

            // send pusher
            // (new \App\Services\PusherNotification)->send(
            //     channel: 'my-channel-' . $manager->employee->user_id,
            //     event: 'new-db-notification',
            //     payload: [
            //         'update' => true
            //     ],
            // );
        }
    }
}
