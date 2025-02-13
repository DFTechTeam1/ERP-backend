<?php

namespace Modules\Production\Jobs;

use App\Enums\Production\TaskSongStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Modules\Production\Notifications\TaskSongApprovedNotification;

class TaskSongApprovedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $task;

    private $user;

    /**
     * Create a new job instance.
     */
    public function __construct(object $task, object $user)
    {
        $this->task = $task;
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // send to worker
        if ($this->task->employee->telegram_chat_id) {
            $message = "Halo {$this->task->employee->nickname}\n";
            $message .= "Tugas kamu di musik {$this->task->song->name} sudah disetujui oleh {$this->user->employee->nickname}";

            $this->task->employee->notify(new TaskSongApprovedNotification([$this->task->employee->telegram_chat_id], $message));
        }

        // send to project PM
        Log::debug("task detail job >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>", [$this->task]);
        if ($this->task->status != TaskSongStatus::Completed->value) {
            foreach ($this->task->project->personInCharges as $pic) {
                if ($pic->employee->telegram_chat_id) {
                    $messagePm = "Halo {$pic->employee->nickname}\n";
                    $messagePm .= "JB di musik {$this->task->song->name} untuk event {$this->task->project->name} sudah bisa di cek.\n";
                    $messagePm .= "Silahkan login untuk melihat detailnya.";
    
                    $pic->employee->notify(new TaskSongApprovedNotification([$pic->employee->telegram_chat_id], $messagePm));
                }
            }
        }
    }
}
