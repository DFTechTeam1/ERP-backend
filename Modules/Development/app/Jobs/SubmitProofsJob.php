<?php

namespace Modules\Development\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Development\Models\DevelopmentProjectTask;
use Modules\Development\Notifications\SubmitProofsNotification;
use Modules\Hrd\Repository\EmployeeRepository;

class SubmitProofsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Collection|DevelopmentProjectTask $task;

    private array $bossIds;

    private \Illuminate\Contracts\Auth\Authenticatable $user;

    /**
     * Create a new job instance.
     */
    public function __construct(
        Collection|DevelopmentProjectTask $task,
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
            $message = "------- DEVELOPMENT TASK -------\n";
            $message .= "Hello {$employee->nickname}\n";
            $message .= "A proof has been submitted for the task:\n\n";
            $message .= "------- TASK DETAILS -------\n";
            $message .= "Task ID: {$this->task->id}\n";
            $message .= "Task Name: {$this->task->name}\n";
            $message .= "Submitted By: {$actor->nickname}\n";
            $message .= "Event: {$this->task->developmentProject->name}\n";

            $employee->notify(new SubmitProofsNotification($message, [$employee->telegram_chat_id]));
        }
    }
}
