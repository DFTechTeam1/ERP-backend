<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReturnEquipmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $projectUid;

    private $pusher;

    /**
     * Create a new job instance.
     */
    public function __construct(string $projectUid)
    {
        $this->projectUid = $projectUid;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->pusher = new \App\Services\PusherNotification;

        $project = \Modules\Production\Models\Project::selectRaw('uid,name')
            ->where('uid', $this->projectUid)
            ->first();

        // get pic inventories
        $users = \App\Models\User::role('it support')->get();

        foreach ($users as $user) {
            $employee = \Modules\Hrd\Models\Employee::selectRaw('id,line_id,telegram_chat_id,name')->where('user_id', $user->id)->first();

            \Illuminate\Support\Facades\Notification::send($employee, new \Modules\Production\Notifications\ReturnEquipmentNotification($employee, $project));

            $notif = formatNotifications($employee->unreadNotifications->toArray());

            $this->pusher->send('my-channel-'.$user->id, 'notification-event', $notif);
        }

    }
}
