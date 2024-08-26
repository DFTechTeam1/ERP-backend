<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

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

            \Illuminate\Support\Facades\Notification::send($employee, new \Modules\Production\Notifications\TaskIsCompleteNotification($employee, $task));
        }
    }
}
