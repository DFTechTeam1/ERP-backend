<?php

namespace Modules\Finance\Jobs;

use App\Services\GeneralService;
use App\Services\RealtimeNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Modules\Finance\Models\ProjectDealPriceChange;
use Modules\Finance\Notifications\NotifyRequestPriceChangesNotification;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectDeal;

class NotifyRequestPriceChangesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $projectDealChangeId;

    private int $newPrice;

    private string $reason;

    /**
     * Create a new job instance.
     */
    public function __construct(int $projectDealChangeId, int $newPrice, string $reason)
    {
        $this->projectDealChangeId = $projectDealChangeId;
        $this->newPrice = $newPrice;
        $this->reason = $reason;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // get director and project deal
        $change = ProjectDealPriceChange::with([
            'requesterBy:id,employee_id',
            'requesterBy.employee:id,name',
        ])->findOrFail($this->projectDealChangeId);
        $projectDealId = $change->project_deal_id;
        $projectDeal = ProjectDeal::findOrFail($projectDealId);
        $employeeUids = (new GeneralService)->getSettingByKey('person_to_approve_invoice_changes');

        $realtimeNotifService = app(RealtimeNotificationService::class);

        $priceChangeUid = Crypt::encryptString($this->projectDealChangeId);
        $requesterName = $change->requesterBy->employee->name;
        $oldPrice = 'Rp. '.number_format($change->old_price, 2);
        $newPrice = 'Rp. '.number_format($change->new_price, 2);

        if ($employeeUids) {
            $employeeUids = json_decode($employeeUids, true);
            $directors = Employee::whereIn('uid', $employeeUids)->get();

            foreach ($directors as $director) {
                $realtimeNotifService->send(
                    recipients: $director,
                    topic: RealtimeNotificationService::TOPIC_GENERAL,
                    payload: [
                        'title' => __('notification.requestPriceChangesInAppTitle'),
                        'message' => __('notification.requestPriceChangesInAppMessage', [
                            'name' => $requesterName,
                            'deal' => $projectDeal->name,
                            'oldPrice' => $oldPrice,
                            'newPrice' => $newPrice,
                        ]),
                        'icon' => '💰',
                        'url' => '/admin/deals/price-changes',
                        'action' => 'request_project_deal_price_changes',
                        'data' => [
                            'price_change_id' => $this->projectDealChangeId,
                            'price_change_uid' => $priceChangeUid,
                            'project_deal_id' => $projectDealId,
                            'old_price' => $change->old_price,
                            'new_price' => $change->new_price,
                            'reason' => $this->reason,
                        ],
                    ],
                );

                // generate approval and rejection URLs
                $approvalUrl = URL::temporarySignedRoute(
                    'project.deal.change.price.approve',
                    now()->addMinutes(30),
                    [
                        'priceChangeId' => $priceChangeUid,
                        'approvalId' => $director->user_id,
                    ]
                );

                $rejectionUrl = URL::temporarySignedRoute(
                    'project.deal.change.price.reject',
                    now()->addMinutes(30),
                    [
                        'priceChangeId' => $priceChangeUid,
                        'approvalId' => $director->user_id,
                    ]
                );

                // send notification
                $director->notify(new NotifyRequestPriceChangesNotification(
                    director: $director,
                    project: $projectDeal,
                    actor: $change->requesterBy->employee,
                    approvalUrl: $approvalUrl,
                    rejectionUrl: $rejectionUrl,
                    reason: $change->reason ? $change->reason->name : $change->custom_reason,
                    oldPrice: $oldPrice,
                    newPrice: $newPrice
                ));
            }
        }
    }
}
