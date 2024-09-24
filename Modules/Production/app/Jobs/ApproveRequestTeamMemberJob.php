<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ApproveRequestTeamMemberJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $ids;

    /**
     * Create a new job instance.
     */
    public function __construct(array $ids)
    {
        $this->ids = $ids;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->ids as $id) {
            $transfer = \Modules\Production\Models\TransferTeamMember::with([
                    'employee:id,name,email,nickname',
                    'requestToPerson:id,name,email,nickname',
                    'requestByPerson:id,name,email,nickname',
                    'project:id,name'
                ])
                ->find($id);

            $requested = \Modules\Hrd\Models\Employee::find($transfer->requested_by);

            $lineIds = [$requested->line_id];

            $employee = \Modules\Hrd\Models\Employee::find($transfer->employee_id);

            $employeeLineIds = [$employee->line_id];

            \Illuminate\Support\Facades\Notification::send($requested, new \Modules\Production\Notifications\ApproveRequestTeamMemberNotification($transfer, $lineIds));

            \Illuminate\Support\Facades\Notification::send($employee, new \Modules\Production\Notifications\PlayerApproveRequestTeamNotification($transfer, $employeeLineIds));
        }

    }
}
