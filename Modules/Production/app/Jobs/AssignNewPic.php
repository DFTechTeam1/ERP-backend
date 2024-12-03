<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Notification;
use Modules\Hrd\Models\Employee;
use Modules\Production\Notifications\NewPicNotification;

class AssignNewPic implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $projectId;

    private $employeeId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $projectId, int $employeeId)
    {
        $this->projectId = $projectId;
        $this->employeeId = $employeeId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $project = \Modules\Production\Models\Project::selectRaw('name,project_date')
            ->find($this->projectId);
        $employee = Employee::find($this->employeeId);

        Notification::send($employee, new NewPicNotification($employee, $project));
    }
}
