<?php

namespace Modules\Production\Jobs;

use App\Services\GeneralService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Hrd\Models\Employee;
use Modules\Production\Notifications\NotifyApprovalProjectDealChangeNotification;
use Modules\Production\Repository\ProjectDealChangeRepository;

class NotifyApprovalProjectDealChangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $changeId;

    private $type;

    /**
     * Create a new job instance.
     */
    public function __construct(int $changeId, string $type)
    {
        $this->changeId = $changeId;
        $this->type = $type;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $change = (new ProjectDealChangeRepository)->show(uid: $this->typw, relation: [
            'projectDeal:id,name',
            'requester:id,employee_id',
            'requester.employee:id,name',
            'approval:id,employee_id',
            'approval.employee:id,name'
        ]);

        $change->requester->notify(new NotifyApprovalProjectDealChangeNotification(
            $change,
            $this->type
        ));
    }
}
