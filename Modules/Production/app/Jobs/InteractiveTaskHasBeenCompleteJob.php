<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Development\Notifications\TaskHasBeenCompleteNotification;
use Modules\Hrd\Repository\EmployeeRepository;

class InteractiveTaskHasBeenCompleteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $currentPicIds;

    private object $task;

    /**
     * Create a new job instance.
     */
    public function __construct(array $currentPicIds, object $task)
    {
        $this->currentPicIds = $currentPicIds;
        $this->task = $task;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $employees = (new EmployeeRepository)->list(
            select: 'id,nickname,telegram_chat_id',
            where: 'id IN ('.implode(',', $this->currentPicIds).') AND telegram_chat_id is not null'
        );

        foreach ($employees as $employee) {
            $message = "------- INTERACTIVE TASK -------\n";
            $message .= "Hello {$employee->nickname}\n";
            $message .= "The task {$this->task->name} in event {$this->task->interactiveProject->name} has been marked as *Completed*.\n";

            $employee->notify(new TaskHasBeenCompleteNotification(
                message: $message,
                chatIds: [$employee->telegram_chat_id]
            ));
        }
    }
}
