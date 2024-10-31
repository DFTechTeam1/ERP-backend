<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CancelProjectWithPicJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $projectUid;

    private $employeeIds;

    /**
     * Create a new job instance.
     * @param array<string>  $employeeIds
     * @param string $projectUid
     */
    public function __construct(array $employeeIds, string $projectUid)
    {
        $this->employeeIds = $employeeIds;
        $this->projectUid = $projectUid;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $project = \Modules\Production\Models\Project::selectRaw('name,project_date')
            ->where("uid", $this->projectUid)
            ->first();

        foreach ($this->employeeIds as $employeeId) {
            $employee = \Modules\Hrd\Models\Employee::selectRaw('id,name,nickname,line_id,telegram_chat_id')
                ->where('employee_id', $employeeId)
                ->first();

            \Illuminate\Support\Facades\Notification::send(
                $employee,
                new \Modules\Production\Notifications\CancelProjectWithPicNotification($employee, $project)
            );
        }
    }
}
