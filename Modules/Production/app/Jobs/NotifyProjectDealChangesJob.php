<?php

namespace Modules\Production\Jobs;

use App\Services\GeneralService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectDealChange;
use Modules\Production\Notifications\NotifyProjectDealChangesNotification;

class NotifyProjectDealChangesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $changesId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $changesId)
    {
        $this->changesId = $changesId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $persons = (new GeneralService)->getSettingByKey('person_to_approve_invoice_changes');

        if ($persons) {
            $persons = json_decode($persons, true);
            $employees = Employee::with('user')->whereIn('uid', $persons)->get();

            $changes = ProjectDealChange::with([
                'requester:id,employee_id',
                'requester.employee:id,nickname',
                'projectDeal:id,name,project_date',
            ])
                ->find($this->changesId);

            foreach ($employees as $employee) {
                // create approval url
                $approvalUrl = (new GeneralService)->generateApprovalUrlForProjectDealChanges(user: $employee->user, changeDeal: $changes, type: 'approved');
                $rejectionUrl = (new GeneralService)->generateApprovalUrlForProjectDealChanges(user: $employee->user, changeDeal: $changes, type: 'rejected');

                $employee->notify(new NotifyProjectDealChangesNotification(
                    $changes,
                    $employee,
                    $approvalUrl,
                    $rejectionUrl
                ));
            }
        }
    }
}