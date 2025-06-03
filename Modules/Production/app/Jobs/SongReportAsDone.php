<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Hrd\Models\Employee;
use Modules\Production\Notifications\SongReportAsDoneNotification;

class SongReportAsDone implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $task;

    private $userId;

    private $worker;

    /**
     * Create a new job instance.
     */
    public function __construct(object $task, int $userId)
    {
        $this->task = $task;

        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->sendToWorker();

        $this->sendToPM();
    }

    protected function sendToPM()
    {
        $entertainmentPic = \App\Models\User::role('project manager entertainment')
            ->with('employee:id,nickname,user_id,telegram_chat_id')
            ->first();

        if (($entertainmentPic) && ($entertainmentPic->employee->telegram_chat_id)) {
            $message = "Halo {$entertainmentPic->employee->nickname}\n";
            $message .= "{$this->worker->nickname} sudah menyelesaikan tugas di musik {$this->task->song->name} untuk event {$this->task->project->name}.\n";
            $message .= 'Kamu bisa mulai mengecek tugas tersebut';

            $entertainmentPic->notify(new SongReportAsDoneNotification([$entertainmentPic->employee->telegram_chat_id], $message));
        }
    }

    protected function sendToWorker()
    {
        // get employee
        $employee = Employee::selectRaw('id,telegram_chat_id,nickname,name,email')
            ->find($this->task->employee_id);

        $this->worker = $employee;

        if ($employee->telegram_chat_id) {
            $message = "Halo {$employee->nickname}\n";
            $message .= "Tugas JB musik {$this->task->song->name} untuk event {$this->task->project->name} sudah selesai dan akan di cek oleh PM.";

            $employee->notify(new SongReportAsDoneNotification([$employee->telegram_chat_id], $message));
        }
    }
}
