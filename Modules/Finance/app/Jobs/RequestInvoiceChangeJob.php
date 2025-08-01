<?php

namespace Modules\Finance\Jobs;

use App\Models\User;
use App\Services\GeneralService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\URL;
use Modules\Finance\Models\InvoiceRequestUpdate;
use Modules\Finance\Notifications\RequestInvoiceChangesNotification;
use Modules\Hrd\Models\Employee;

class RequestInvoiceChangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $model;

    /**
     * Create a new job instance.
     */
    public function __construct(InvoiceRequestUpdate $model)
    {
        $this->model = $model;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $output = (new GeneralService)->getDataForRequestInvoiceChangeNotification(invoiceRequestId: $this->model->id);
        $data = $output['invoice'];
        $changes = $output['changes'];
        $actor = $output['actor'];

        // get people's who will get this notification
        $persons = (new GeneralService)->getSettingByKey('person_to_approve_invoice_changes');

        if ($persons) {
            $persons = json_decode($persons, true);
            $employees = Employee::with('user')->whereIn('uid', $persons)->get();

            foreach ($employees as $employee) {
                // define approval and rejection url
                $approvalUrl = URL::signedRoute(
                    name: 'api.invoices.approveChanges',
                    parameters: [
                        'invoiceUid' => $data->invoice->uid,
                        'dir' => $employee->user->uid,
                        'cid' => $data->id
                    ],
                    expiration: now()->addHours(5)
                );

                // create rejection url with signed route
                $rejectionUrl = URL::signedRoute(
                    name: 'api.invoices.rejectChanges',
                    parameters: [
                        'invoiceUid' => $data->invoice->uid,
                        'dir' => $employee->user->uid,
                        'cid' => $data->id
                    ],
                    expiration: now()->addHours(5)
                );

                $output['approvalUrl'] = $approvalUrl;
                $output['rejectionUrl'] = $rejectionUrl;

                $telegramIds = $employee->telegram_chat_id ? [$employee->telegram_chat_id] : [];
        
                // define message for telegram
                $mainMessage = "🔔Approval Required\n📋 Invoice: *[{$data->invoice->number} *]\n\n👤 Client: {$data->invoice->customer->name}\n";
                foreach ($changes as $field => $change) {
                    $old = $change['old'];
                    $new = $change['new'];
                    $mainMessage .= "✏️ Modified Field: {$field}\n🔄 Change: {$old} → {$new}\n";
                }
                $mainMessage .= "👨‍💼 Requested By: {$actor->employee->name}";
                $message = [
                    $mainMessage,
                    [
                        'text' => 'Approved changes?',
                        'type' => 'inline_keyboard',
                        'keyboard' => [
                            'inline_keyboard' => [
                                [
                                    ['text' => 'Approve Changes', 'callback_data' => "approve"],
                                    ['text' => 'Reject Changes', 'callback_data' => "approve"],
                                ],
                            ],
                        ],
                    ],
                ];
        
                $employee->notify(new RequestInvoiceChangesNotification($output, $telegramIds, $message));
            }
        }

    }
}
