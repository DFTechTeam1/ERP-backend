<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class AssignTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $employeeIds;

    public $taskId;

    /**
     * Create a new job instance.
     */
    public function __construct($employeeIds, $taskId)
    {
        $this->employeeIds = $employeeIds;

        $this->taskId = $taskId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $task = \Modules\Production\Models\ProjectTask::selectRaw('name,project_id')
            ->with(['project:id,name'])
            ->find($this->taskId);

        $employees = [];
        $lineIds = [];
        foreach ($this->employeeIds as $employee) {
            $data = \Modules\Hrd\Models\Employee::selectRaw('line_id,id,uid,name,email')
                ->find($employee);

            if ($data->line_id) {
                $employees[] = $data;
                $lineIds[] = $data->line_id;
            }
        }

        \Illuminate\Support\Facades\Notification::send($employees, new \Modules\Production\Notifications\AssignTaskNotification($lineIds, $task));
    }
}
