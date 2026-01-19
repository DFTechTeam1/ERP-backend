<?php

namespace Modules\Production\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Modules\Production\Notifications\DistributeSongNotification;

class DistributeSongJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $employeeUid;

    public $songUid;

    public $projectUid;

    /**
     * Create a new job instance.
     */
    public function __construct(string $employeeUid, string $projectUid, string $songUid)
    {
        $this->employeeUid = $employeeUid;
        $this->songUid = $songUid;
        $this->projectUid = $projectUid;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $employee = Employee::selectRaw('id,nickname,telegram_chat_id')
            ->where('uid', $this->employeeUid)
            ->first();
        $song = ProjectSongList::selectRaw('id,name')
            ->where('uid', $this->songUid)
            ->first();
        $project = Project::selectRaw('id,name')
            ->where('uid', $this->projectUid)
            ->first();

        $message = "You have been assigned to work on this '{$song->name}' song for project '{$project->name}'.";

        $user = User::where('employee_id', $employee->id)
            ->first();

        $user->notify(new DistributeSongNotification([$employee->telegram_chat_id], $message, $this->projectUid));

        // Send to pusher notification
        $pusher = new \App\Services\PusherNotification();
        $pusher->send('my-channel-'.$user->id, 'new-db-notification', [
            'update' => true,
            'st' => true, // stand for stand for
            'm' => 'You have been assigned a new task', // stand for message
            't' => 'New Task', // stand for title
        ]);
    }
}
