<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ReviseTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $employeeIds;

    private $taskId;

    private $pusher;

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
        $this->pusher = new \App\Services\PusherNotification();

        $task = \Modules\Production\Models\ProjectTask::selectRaw('name,project_id')
            ->with([
                'project:id,name,uid'
            ])
            ->find($this->taskId);

        $revise = \Modules\Production\Models\ProjectTaskReviseHistory::selectRaw('reason')
                ->where('project_task_id', $this->taskId)
                ->orderBy('created_at', 'desc')
                ->first();

        foreach ($this->employeeIds as $employeeId) {
            $employee = \Modules\Hrd\Models\Employee::find($employeeId);
            
            \Illuminate\Support\Facades\Notification::send($employee, new \Modules\Production\Notifications\ReviseTaskNotification($employee, $task, $revise));

            $notif = formatNotifications($employee->unreadNotifications->toArray());

            $this->pusher->send('my-channel-' . $employee->user_id, 'notification-event', $notif);
        }
    }
}
