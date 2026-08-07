<?php

namespace Modules\Production\Jobs;

use App\Services\RealtimeNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
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
        $realtimeNotifService = app(RealtimeNotificationService::class);

        $change = app(ProjectDealChangeRepository::class)->show([
            'where' => ['id' => $this->changeId],
            'with' => [
                'projectDeal:id,name',
                'requester:id,employee_id',
                'requester.employee:id,name,email',
                'approval:id,employee_id',
                'approval.employee:id,name',
                'rejecter:id,employee_id',
                'rejecter.employee:id,name',
            ],
        ]);

        if (! $change) {
            return;
        }

        $requester = $change->requester?->employee;

        // the change is the requester's own record, so with nobody to notify
        // there is nothing left for this job to do
        if (! $requester) {
            return;
        }

        $isApproved = $this->type == 'approved';
        $langKey = $isApproved ? 'dealChangesApproved' : 'dealChangesRejected';

        $approvalName = ($isApproved ? $change->approval?->employee?->name : $change->rejecter?->employee?->name) ?? '-';

        $requester->notify(new NotifyApprovalProjectDealChangeNotification(
            $change,
            $this->type,
            $approvalName
        ));

        $realtimeNotifService->send(
            recipients: $requester,
            topic: RealtimeNotificationService::TOPIC_GENERAL,
            payload: [
                'title' => __("notification.{$langKey}Title"),
                'message' => __("notification.{$langKey}Message", [
                    'name' => $approvalName,
                    'deal' => $change->projectDeal->name,
                ]),
                'icon' => $isApproved ? '✅' : '❌',
                'url' => '/admin/deals/changes',
                'action' => $isApproved
                    ? 'project_deal_change_approved'
                    : 'project_deal_change_rejected',
                'data' => [
                    'deal_change_id' => $change->id,
                    'deal_change_uid' => Crypt::encryptString($change->id),
                    'project_deal_id' => $change->project_deal_id,
                    'type' => $this->type,
                    'decided_by' => $approvalName,
                ],
            ],
        );
    }
}
