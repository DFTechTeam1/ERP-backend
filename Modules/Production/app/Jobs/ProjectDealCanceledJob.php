<?php

namespace Modules\Production\Jobs;

use App\Services\GeneralService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Notifications\ProjectDealCanceledNotification;
use Modules\Production\Repository\ProjectDealRepository;

class ProjectDealCanceledJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $projectDealId;

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
        $projectDeal = (new ProjectDealRepository)->show(uid: $this->projectDealId, select: 'id,name,project_date,cancel_reason', relation: [
            'marketings',
            'marketings.employee:id,name',
        ]);

        $uids = json_decode((new GeneralService)->getSettingByKey('person_to_approve_invoice_changes'), true);

        if ($uids) {
            $uids = "'".implode("','", $uids)."'";
            $employees = (new EmployeeRepository)->list(select: 'id,name,email,nickname,telegram_chat_id', where: "uid IN ({$uids})");

            foreach ($employees as $employee) {
                $message = "Hello {$employee->nickname}\n";
                $message .= "Event {$projectDeal->name} has been canceled because of {$projectDeal->cancel_reason}";

                $employee->notify(new ProjectDealCanceledNotification($message, [$employee->telegram_chat_id]));
            }
        }
    }
}
