<?php

namespace Modules\Finance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Finance\Models\InvoiceRequestUpdate;
use Modules\Finance\Notifications\RejectInvoiceChangesNotification;

class RejectInvoiceChangesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $invoiceUpdateId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $invoiceUpdateId)
    {
        $this->invoiceUpdateId = $invoiceUpdateId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $currentChanges = InvoiceRequestUpdate::selectRaw('id,request_by,amount,payment_date,invoice_id,approved_at')
            ->with([
                'user:id,email,employee_id',
                'user.employee:id,name',
                'invoice:id,parent_number,number'
            ])
            ->find($this->invoiceUpdateId);

        $currentChanges->user->notify(new RejectInvoiceChangesNotification(invoice: $currentChanges));
    }
}
