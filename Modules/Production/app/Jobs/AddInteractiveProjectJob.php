<?php

namespace Modules\Production\Jobs;

use App\Services\GeneralService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Notifications\AddInteractiveProjectNotification;
use Modules\Production\Repository\InteractiveRequestRepository;

class AddInteractiveProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $projectDealId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $projectDealId)
    {
        $this->projectDealId = $projectDealId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $request = (new InteractiveRequestRepository)->show(
            uid: 'id',
            select: '*',
            where: "project_deal_id = {$this->projectDealId}",
            relation: [
                'projectDeal:id,name,project_date',
                'projectDeal.latestQuotation',
                'requester:id,employee_id',
                'requester.employee:id,name,telegram_chat_id',
            ]
        );

        // director
        $persons = (new GeneralService)->getSettingByKey('person_to_approve_invoice_changes');

        if (! $persons) {
            $implodeString = "'".implode("','", $persons)."'";
            $employees = (new EmployeeRepository)->list(
                select: 'id,name,email',
                where: "uid IN ({$implodeString})"
            );

            foreach ($employees as $employee) {
                $message = "Hello {$employee->name},\n\n";
                $message .= "There is a new interactive project request from {$request->requester->employee->name} for project {$request->projectDeal->name} dated {$request->projectDeal->project_date}.\n";
                $message .= "Please review and take the necessary actions.\n\n";
                $message .= 'Thank you.';

                $employee->notify(new AddInteractiveProjectNotification($message, $employee, $request));
            }
        }
    }
}
