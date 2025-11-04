<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class NotifyHoldTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly int $taskId,
        private readonly int $actorId
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $taskHold = (new \Modules\Production\Repository\ProjectTaskPicHoldstateRepository)->show(
            uid: 0,
            select: 'id,task_id,reason',
            relation: [
                'task:id,name,project_id',
                'task.project:id,uid,name',
                'task.project.personInCharges:id,project_id,pic_id',
                'task.project.personInCharges.employee:id,name,nickname'
            ],
            where: "task_id = {$this->taskId}"
        );

        $actor = (new \Modules\Hrd\Repository\EmployeeRepository)->show(
            uid: 'id',
            select: 'id,name,nickname',
            where: "id = {$this->actorId}"
        );

        $pics = $taskHold->task->project->personInCharges;

        foreach ($pics as $pic) {
            \App\Services\NotificationService::send(
                recipients: $pic->employee,
                action: 'task_has_been_hold_by_user',
                data: [
                    'parameter1' => $pic->employee->nickname,
                    'parameter2' => $taskHold->task->name,
                    'parameter3' => $taskHold->task->project->name,
                    'parameter4' => $actor->nickname,
                    'parameter5' => $taskHold->reason,
                ],
                channels: ['database'],
                options: [
                    'url' => config('app.frontend_url') . '/admin/production/project/' . $taskHold->task->project->uid,
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
