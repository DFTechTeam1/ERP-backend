<?php

namespace Modules\Finance\Jobs;

use App\Services\GeneralService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
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
        $change = ProjectDealPriceChange::findOrFail($this->projectDealChangeId);
        $projectDealId = $change->project_deal_id;
        $projectDeal = ProjectDeal::findOrFail($projectDealId);
        $employeeUids = (new GeneralService)->getSettingByKey('person_to_approve_invoice_changes');

        if ($employeeUids) {
            $employeeUids = json_decode($employeeUids, true);
            $directors = Employee::whereIn('uid', $employeeUids)->get();

            foreach ($directors as $director) {
                // generate approval and rejection URLs
                $approvalUrl = URL::temporarySignedRoute(
                    'project.deal.change.price.approve',
                    now()->addMinutes(30),
                    [
                        'priceChangeId' => Crypt::encryptString($this->projectDealChangeId),
                        'approvalId' => $director->user_id
                    ]
                );

                $rejectionUrl = URL::temporarySignedRoute(
                    'project.deal.change.price.reject',
                    now()->addMinutes(30),
                    [
                        'priceChangeId' => Crypt::encryptString($this->projectDealChangeId),
                        'approvalId' => $director->user_id
                    ]
                );

                // send notification
                $director->notify(new NotifyRequestPriceChangesNotification(
                    director: $director,
                    project: $projectDeal,
                    approvalUrl: $approvalUrl,
                    rejectionUrl: $rejectionUrl,
                    reason: $change->reason ? $change->reason->name : $change->custom_reason,
                    oldPrice: "Rp. " . number_format($change->old_price, 2),
                    newPrice: "Rp. " . number_format($change->new_price, 2)
                ));
            }
        }
    }
}
