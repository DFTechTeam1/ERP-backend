<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class AssignVjJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $project;

    public $employees;

    /**
     * Create a new job instance.
     * @param object $project
     * @param array<string, array<string>> $employees
     * $employees will have:
     * employee_id
     */
    public function __construct($project, array $employees)
    {
        $this->project = $project;

        $this->employees = $employees;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $pusher = new \App\Services\PusherNotification();

        $vj = \Modules\Production\Models\ProjectVj::with('creator')->where('project_id', $this->project->id)->first();

        logging('vj', $vj->toArray());

        $creator = $vj->creator ? $vj->creator->nickname : 'admin';

        foreach ($this->employees['employee_id'] as $employee) {
            $employeeData = \Modules\Hrd\Models\Employee::where('uid', $employee)->first();

            $telegramChatIds = [$employeeData->telegram_chat_id];

            \Illuminate\Support\Facades\Notification::send($employeeData, new \Modules\Production\Notifications\AssignVjNotification($telegramChatIds, $this->project, $employeeData, $creator));

            $user = \App\Models\User::select('id')->where('employee_id', $employeeData->id)->first();

            $output = formatNotifications($employeeData->unreadNotifications->toArray());

            $pusher->send('my-channel-' . $user->id, 'notification-event', $output);
        }
    }
}
