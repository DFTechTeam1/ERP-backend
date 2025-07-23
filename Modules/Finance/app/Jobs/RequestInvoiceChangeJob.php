<?php

namespace Modules\Finance\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
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
        $data = \Modules\Finance\Models\InvoiceRequestUpdate::with([
                'invoice:id,parent_number,amount,payment_date,customer_id,project_deal_id,number',
                'invoice.customer:id,name',
                'invoice.projectDeal:id,name'
            ])
            ->find($this->model->id);

        $changes = [];
        if (
            ($data->invoice->amount != $data->amount) &&
            ($data->amount)
        ) {
            $changes['amount'] = [
                'old' => "Rp" . number_format(num: $data->invoice->amount, decimal_separator: ','),
                'new' => "Rp" . number_format(num: $data->amount, decimal_separator: ',')
            ];
        }
        if (
            (date('Y-m-d', strtotime($data->invoice->payment_date)) != date('Y-m-d', strtotime($data->payment_date))) &&
            ($data->payment_date)
        ) {
            $changes['payment_date'] = [
                'old' => date('Y-m-d', strtotime($data->invoice->payment_date)),
                'new' => $data->payment_date
            ];
        }

        $actor = \App\Models\User::with(['employee:id,user_id,name'])
            ->find($data->request_by);

        $director = \Modules\Hrd\Models\Employee::where('email', 'wesleywiyadi@gmail.com') 
            ->first();

        $output = [
            'actor' => $actor,
            'invoice' => $data,
            'director' => $director,
            'changes' => $changes,
            'approvalUrl' => '',
            'rejectionUrl' => ''
        ];

        $telegramIds = $director->telegram_chat_id ? [$director->telegram_chat_id] : [];

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

        $director->notify(new RequestInvoiceChangesNotification($output, $telegramIds, $message));
    }
}
