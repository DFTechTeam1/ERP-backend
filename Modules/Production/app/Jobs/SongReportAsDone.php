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

    private $pusher;

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
        // Send to pusher notification
        $this->pusher = new \App\Services\PusherNotification();

        $this->sendToWorker();

        $this->sendToPM();

    }

    protected function sendToPM()
    {
        $entertainmentPic = \App\Models\User::role('project manager entertainment')
            ->with('employee:id,nickname,user_id,telegram_chat_id')
            ->first();

        if ($entertainmentPic) {
            $message = "A new task has been submitted and is ready for your review";

            $entertainmentPic->notify(new SongReportAsDoneNotification($message, $this->task->project->uid));

            $this->pusher->send('my-channel-'.$entertainmentPic->id, 'new-db-notification', [
                'update' => true,
                'st' => true, // stand for stand for
                'm' => 'New Task to Review', // stand for message
                't' => 'Task to Review', // stand for title
            ]);
        }
    }

    protected function sendToWorker()
    {
        $user = \App\Models\User::where('employee_id', $this->task->employee_id)->first();

        if ($user) {
            $message = "Your task has been uploaded and is now under review by Project Manager";
    
            $user->notify(new SongReportAsDoneNotification($message, $this->task->project->uid));

            $this->pusher->send('my-channel-'.$user->id, 'new-db-notification', [
                'update' => true,
                'st' => true, // stand for stand for
                'm' => 'Task submitted. PM reviewing', // stand for message
                't' => 'Task submitted', // stand for title
            ]);
        }
    }
}
