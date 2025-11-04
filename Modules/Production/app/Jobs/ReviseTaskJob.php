<?php

namespace Modules\Production\Jobs;

use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReviseTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $pusher;

    /**
     * Create a new job instance.
     *
     * @param  array<int>  $employeeIds
     */
    public function __construct(
        private readonly array $employeeIds,
        private readonly int $taskId
    )
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->pusher = new \App\Services\PusherNotification;

        $action = 'task_has_been_revise_by_pic';

        $task = \Modules\Production\Models\ProjectTask::selectRaw('name,project_id')
            ->with([
                'project:id,name,uid',
            ])
            ->find($this->taskId);

        $revise = \Modules\Production\Models\ProjectTaskReviseHistory::selectRaw('reason,file,project_id,project_task_id,revise_by')
            ->where('project_task_id', $this->taskId)
            ->with([
                'reviseBy:id,nickname'
            ])
            ->orderBy('created_at', 'desc')
            ->first();

        foreach ($this->employeeIds as $employeeId) {
            $employee = \Modules\Hrd\Models\Employee::find($employeeId);

            NotificationService::send(
                recipients: $employee,
                action: $action,
                data: [
                    'parameter1' => $employee->nickname,
                    'parameter2' => $task->name,
                    'parameter3' => $task->project->name,
                    'parameter4' => $revise->reviseBy->nickname,
                    'parameter5' => $revise->reason,
                ],
                channels: ['database'],
                options: [
                    'url' => config('app.frontend_url') . '/admin/production/project/' . $task->project->uid,
                    'database_type' => 'production'
                ]
            );
        }
    }
}
