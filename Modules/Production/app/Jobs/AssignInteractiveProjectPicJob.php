<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Models\InteractiveProject;
use Modules\Production\Notifications\AssignInteractiveProjectPicNotification;

class AssignInteractiveProjectPicJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Collection|InteractiveProject $project;

    private array $employeeIds;

    /**
     * Create a new job instance.
     */
    public function __construct(Collection|InteractiveProject $project, array $employeeIds)
    {
        $this->project = $project;
        $this->employeeIds = $employeeIds;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $employees = (new EmployeeRepository)->list(
            select: 'id,email,telegram_chat_id',
            where: 'id IN ('.implode(',', array_column($this->employeeIds, 'employee_id')).')',
        );

        foreach ($employees as $employee) {
            $employee->notify(new AssignInteractiveProjectPicNotification($this->project, $employee));
        }
    }
}
