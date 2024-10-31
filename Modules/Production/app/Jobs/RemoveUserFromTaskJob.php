<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class RemoveUserFromTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $employeeUids;

    private $taskId;

    /**
     * Create a new job instance.
     * @param array<string> $employeeUids
     * @param int $taskId
     */
    public function __construct(array $employeeUids, int $taskId)
    {
        $this->employeeUids = $employeeUids;

        $this->taskId = $taskId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $task = \Modules\Production\Models\ProjectTask::selectRaw('id,project_id,name')->find($this->taskId);

        foreach ($this->employeeUids as $employeeUid) {
            $employeeId = getIdFromUid($employeeUid, new \Modules\Hrd\Models\Employee());
            $employee = \Modules\Hrd\Models\Employee::find($employeeId);

            \Illuminate\Support\Facades\Notification::send($employee, new \Modules\Production\Notifications\RemoveUserFromTaskNotification($employee, $task));
        }
    }
}
