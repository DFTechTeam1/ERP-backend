<?php

namespace Modules\Production\Jobs;

use App\Enums\System\BaseRole;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Modules\Production\Notifications\ConfirmDeleteSongNotification;

class ConfirmDeleteSongJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $currentSongName;

    public $currentWorkerId;

    public $projectUid;

    /**
     * Create a new job instance.
     */
    public function __construct($currentSongName, $currentWorkerId, $projectUid)
    {
        $this->currentSongName = $currentSongName;
        $this->currentWorkerId = $currentWorkerId;
        $this->projectUid = $projectUid;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $employee = User::where('employee_id', $this->currentWorkerId)->first();
        $entertainmentPic = \App\Models\User::role(BaseRole::ProjectManagerEntertainment->value)
            ->with('employee:id,nickname,user_id,telegram_chat_id')
            ->first();

        if ($entertainmentPic) {
            $project = Project::selectRaw('id,name')
                ->where('uid', $this->projectUid)
                ->first();

            $message = "The song {$this->currentSongName} has been successfully deleted from project {$project->name}. You can proceed with your next tasks.";

            $employee->notify(new ConfirmDeleteSongNotification($message, $this->projectUid));

            $pusher = new \App\Services\PusherNotification();
            $pusher->send('my-channel-'.$employee->id, 'new-db-notification', [
                'update' => true,
                'st' => true, // stand for stand for
                'm' => "The song you're working on has been deleted.", // stand for message
                't' => 'Confirm Delete Song', // stand for title
            ]);
        }
    }
}
