<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class RemovePMFromProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $pics;

    private $projectUid;

    /**
     * Create a new job instance.
     * @param array $pics
     * @param string $projectUid
     */
    public function __construct(array $pics, string $projectUid)
    {
        $this->pics = $pics;

        $this->projectUid = $projectUid;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $pusher = new \App\Services\PusherNotification();

        $employeeRepo = new \Modules\Hrd\Repository\EmployeeRepository();

        $projectRepo = new \Modules\Production\Repository\ProjectRepository();

        $project = $projectRepo->show($this->projectUid, 'id,name,project_date');

        $uids = implode(',', $this->pics);

        $employees = $employeeRepo->list(
            'id,uid,name,line_id,nickname,telegram_chat_id',
            "id IN ({$uids})",
        );

        foreach ($employees as $employee) {
            \Illuminate\Support\Facades\Notification::send($employee, new \Modules\Production\Notifications\RemovePMFromProjectNotification($project, $employee));

            $user = \App\Models\User::select('id')
                ->where('employee_id', $employee->id)
                ->first();

            $output = formatNotifications($employee->unreadNotifications->toArray());

            $pusher->send('my-channel-' . $user->id, 'notification-event', $output);
        }
    }
}
