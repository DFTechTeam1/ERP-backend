<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class AssignCheckByPMJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $employeeIds;

    public $taskId;

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
        $task = \Modules\Production\Models\ProjectTask::selectRaw('name,project_id,id,uid')
            ->with([
                'project:id,name,uid',
                'pics:id,project_task_id,employee_id',
                'pics.employee:id,nickname',
            ])
            ->find($this->taskId);

        $employees = [];
        foreach ($this->employeeIds as $employee) {
            $data = \Modules\Hrd\Models\Employee::selectRaw('line_id,id,uid,name,email,telegram_chat_id')
                ->find($employee);

            if ($data->line_id) {
                $employees[] = $data;

                \Illuminate\Support\Facades\Notification::send(
                    $employees,
                    new \Modules\Production\Notifications\AssignCheckByPMNotification([$data->telegram_chat_id], $task, $employee)
                );
            }

        }
    }
}
