<?php

namespace Modules\Production\Jobs;

use App\Services\PusherNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class NewProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $project;

    private $pusher;

    /**
     * Create a new job instance.
     */
    public function __construct($project)
    {
        $this->project = $project;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->pusher = new PusherNotification();
        
        $employee = \Modules\Hrd\Models\Employee::where('email', 'wesleywiyadi@gmail.com')->first();
        
        \Illuminate\Support\Facades\Notification::send($employee, new \Modules\Production\Notifications\NewProjectNotification($this->project));
        
        $user = \App\Models\User::select('id')
            ->where('employee_id', $employee->id)
            ->first();

        $output = formatNotifications($employee->unreadNotifications->toArray());

        $this->pusher->send('my-channel-' . $user->id, 'notification-event', $output);
    }
}
