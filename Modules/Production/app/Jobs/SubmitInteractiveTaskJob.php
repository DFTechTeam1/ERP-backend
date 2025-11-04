<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Development\Notifications\SubmitProofsNotification;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Models\InteractiveProjectTask;

class SubmitInteractiveTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Collection|InteractiveProjectTask $task;

    private array $bossIds;

    private \Illuminate\Contracts\Auth\Authenticatable $user;

    /**
     * Create a new job instance.
     */
    public function __construct(
        Collection|InteractiveProjectTask $task,
        array $bossIds,
        \Illuminate\Contracts\Auth\Authenticatable $user
    ) {
        $this->task = $task;
        $this->bossIds = $bossIds;
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $employees = (new EmployeeRepository)->list(select: 'id,nickname,telegram_chat_id', where: 'id IN ('.implode(',', $this->bossIds).') AND telegram_chat_id IS NOT NULL');

        $actor = (new EmployeeRepository)->show(uid: 'uid', select: 'id,nickname', where: 'user_id = '.$this->user->id);

        foreach ($employees as $employee) {
            $message = "------- INTERACTIVE TASK -------\n";
            $message .= "Hello {$employee->nickname}\n";
            $message .= "A proof has been submitted for the task:\n\n";
            $message .= "------- TASK DETAILS -------\n";
            $message .= "Task ID: {$this->task->id}\n";
            $message .= "Task Name: {$this->task->name}\n";
            $message .= "Submitted By: {$actor->nickname}\n";
            $message .= "Event: {$this->task->interactiveProject->name}\n";

            $employee->notify(new SubmitProofsNotification($message, [$employee->telegram_chat_id]));
        }
    }
}
