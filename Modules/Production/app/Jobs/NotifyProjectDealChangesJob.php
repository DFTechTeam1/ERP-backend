<?php

namespace Modules\Production\Jobs;

use App\Services\GeneralService;
use App\Services\RealtimeNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
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

            if (! $changes) {
                return;
            }

            $realtimeNotifService = app(RealtimeNotificationService::class);

            $changeUid = Crypt::encryptString($changes->id);
            $requesterName = $changes->requester?->employee?->nickname ?? '-';
            $changedFields = collect($changes->detail_changes)
                ->pluck('label')
                ->filter()
                ->implode(', ');

            foreach ($employees as $employee) {
                $realtimeNotifService->send(
                    recipients: $employee,
                    topic: RealtimeNotificationService::TOPIC_GENERAL,
                    payload: [
                        'title' => __('notification.requestDealChangesInAppTitle'),
                        'message' => __('notification.requestDealChangesInAppMessage', [
                            'name' => $requesterName,
                            'deal' => $changes->projectDeal->name,
                            'fields' => $changedFields,
                        ]),
                        'icon' => '📝',
                        'url' => '/admin/deals/changes',
                        'action' => 'request_project_deal_changes',
                        'data' => [
                            'deal_change_id' => $changes->id,
                            'deal_change_uid' => $changeUid,
                            'project_deal_id' => $changes->project_deal_id,
                            'project_date' => $changes->projectDeal->project_date,
                            'detail_changes' => $changes->detail_changes,
                        ],
                    ],
                );

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
