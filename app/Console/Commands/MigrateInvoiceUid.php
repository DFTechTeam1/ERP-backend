<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MigrateInvoiceUid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-invoice-uid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will insert new UID to exisiting invoices (if uid is not set)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $invoices = \Modules\Finance\Models\Invoice::whereNull('uid')->get();

        if ($invoices->isEmpty()) {
            $this->info('No invoices found without UID.');
            return;
        }

        foreach ($invoices as $invoice) {
            $invoice->uid = \Illuminate\Support\Str::uuid();
            $invoice->save();
            $this->info("Invoice ID {$invoice->id} updated with UID: {$invoice->uid}");
        }

        $this->info('All invoices have been processed.');
        return 0;
    }
}
