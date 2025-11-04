<?php

namespace Modules\Development\Jobs;

use App\Enums\Development\Project\Task\TaskStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Development\Models\DevelopmentProjectTask;
use Modules\Development\Notifications\NotifyTaskAssigneeNotification;
use Modules\Hrd\Repository\EmployeeRepository;

class NotifyTaskAssigneeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $asignessIds;

    private Collection|DevelopmentProjectTask $task;

    /**
     * Create a new job instance.
     */
    public function __construct(array $asignessIds, Collection|DevelopmentProjectTask $task)
    {
        $this->asignessIds = $asignessIds;
        $this->task = $task;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // build message
        $ids = implode("','", $this->asignessIds);
        $employees = (new EmployeeRepository)->list(
            select: 'id,telegram_chat_id,name',
            where: "id IN ('{$ids}')"
        );

        $dealine = $this->task->deadline ? date('d F Y H:i', strtotime($this->task->deadline)) : '-';

        $isAlreadyRunning = $this->task->status == TaskStatus::InProgress ? true : false;

        foreach ($employees as $employee) {
            // send notification
            if ($employee->telegram_chat_id) {
                $message = "---- DEVELOPMENT TASK ----\n";
                $message .= "Task Name: {$this->task->name}\n";
                $message .= "Due Date: {$dealine}\n";
                $message .= "Assigned To: {$employee->name}\n";

                if ($isAlreadyRunning) {
                    $message .= "Status: On Progress\n";
                    $message .= "Note: This task is already in progress. Please check the task details and proceed accordingly.\n";
                } else {
                    $message .= "Status: New Task Assigned\n";
                    $message .= "Note: You have been assigned a new task. Please check the task details and start working on it.\n";
                }

                // Send Telegram notification
                $notification = new NotifyTaskAssigneeNotification(
                    telegramChatIds: [$employee->telegram_chat_id],
                    message: $message
                );
                $employee->notify($notification);
            }
        }
    }
}
