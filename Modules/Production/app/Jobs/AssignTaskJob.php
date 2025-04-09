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

    public $userData;

    /**
     * Create a new job instance.
     */
    public function __construct(array $employeeIds, int $taskId, object $userData)
    {
        $this->employeeIds = $employeeIds;

        $this->taskId = $taskId;

        $this->userData = $userData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // TODO: CHECK AGAIN ACTION WHEN ASSIGN TO PROJECT MANAGER
        $task = \Modules\Production\Models\ProjectTask::selectRaw('name,project_id,id,uid')
            ->with(['project:id,name,uid'])
            ->find($this->taskId);

        $employees = [];
        foreach ($this->employeeIds as $employee) {
            $data = \Modules\Hrd\Models\Employee::selectRaw('line_id,id,uid,name,email,telegram_chat_id')
                ->find($employee);

            if ($data->line_id) {
                $employees[] = $data;

                \Illuminate\Support\Facades\Notification::send(
                    $employees,
                    new \Modules\Production\Notifications\AssignTaskNotification([$data->telegram_chat_id], $task, $employee, $this->userData)
                );
            }

        }
    }
}
