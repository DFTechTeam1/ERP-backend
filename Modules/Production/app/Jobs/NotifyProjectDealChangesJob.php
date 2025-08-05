<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use App\Services\GeneralService;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectDealChange;
use Modules\Production\Notifications\NotifyProjectDealChangesNotification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

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
                    'projectDeal:id,name,project_date'
                ])
                ->find($this->changesId);

            foreach ($employees as $employee) {
                $employee->notify(new NotifyProjectDealChangesNotification($changes, $employee));
            }
        }
    }
}
