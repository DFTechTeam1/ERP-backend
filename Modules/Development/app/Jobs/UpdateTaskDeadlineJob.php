<?php

namespace Modules\Development\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Development\Models\DevelopmentProjectTask;
use Modules\Development\Notifications\UpdateTaskDeadlineNotification;

class UpdateTaskDeadlineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Collection|DevelopmentProjectTask $task;

    private array $payload;

    /**
     * Create a new job instance.
     */
    public function __construct(Collection|DevelopmentProjectTask $task, array $payload)
    {
        $this->task = $task;
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $deadline = Carbon::parse($this->payload['end_date'])->format('d F Y H:i');

        // filter pics based on employee.telegram_chat_id
        $filteredPics = $this->task->pics->filter(function ($pic) {
            return ! empty($pic->employee->telegram_chat_id);
        });

        if ($filteredPics->isNotEmpty()) {
            foreach ($filteredPics as $pic) {
                // build message notification to send via telegram. Message should be on string
                $message = "-------- DEVELOPMENT TASK --------\n";
                $message .= "Deadline for task '{$this->task->name}' on the event {$this->task->developmentProject->name} has been updated.\n";
                $message .= "New deadline: {$deadline}";
                $pic->employee->notify(new UpdateTaskDeadlineNotification($message, [$pic->employee->telegram_chat_id]));
            }
        }
    }
}
