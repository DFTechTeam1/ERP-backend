<?php

namespace Modules\Production\Jobs;

use App\Services\PusherNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NewProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $project;

    public $projectUid;

    private $pusher;

    /**
     * Create a new job instance.
     */
    public function __construct(string $projectUid)
    {
        $this->projectUid = $projectUid;
    }

    /**
     * Notify selected project manager and current entertainment lead
     */
    public function handle(): void
    {
        $this->project = \Modules\Production\Models\Project::where('uid', $this->projectUid)->first();

        $this->pusher = new PusherNotification;

        $projectPic = \Modules\Production\Models\ProjectPersonInCharge::select('pic_id')->where('project_id', $this->project->id)->get();

        foreach ($projectPic as $pic) {
            $employee = \Modules\Hrd\Models\Employee::find($pic->pic_id);

            \Illuminate\Support\Facades\Notification::send($employee, new \Modules\Production\Notifications\NewProjectNotification($this->project, $employee));

            $user = \App\Models\User::select('id')
                ->where('employee_id', $employee->id)
                ->first();

            $output = formatNotifications($employee->unreadNotifications->toArray());

            $this->pusher->send('my-channel-'.$user->id, 'notification-event', $output);
        }

        $this->notifyEntertainment();
    }

    protected function notifyEntertainment()
    {
        // get user with 'pic entertainment' role
        $users = \App\Models\User::role('pic entertainment')->get();

        foreach ($users as $user) {
            if ($user->employee_id) {
                $employee = \Modules\Hrd\Models\Employee::find($user->employee_id);

                \Illuminate\Support\Facades\Notification::send($employee, new \Modules\Production\Notifications\NewProjectForEntertainmentNotification($this->project, $employee));
            }

            $notif = formatNotifications($employee->unreadNotifications->toArray());

            $this->pusher->send('my-channel-'.$user->id, 'notification-event', $notif);
        }
    }
}
