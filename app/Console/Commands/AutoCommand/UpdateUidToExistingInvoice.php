<?php

namespace App\Console\Commands\AutoCommand;

use Illuminate\Console\Command;

class UpdateUidToExistingInvoice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-uid-to-existing-invoice';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Here we insert ordered uid to existing invoice data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $invoices = \Modules\Finance\Models\Invoice::selectRaw('id,uid')
            ->whereNull('uid')
            ->get();

        $count = 0;
        foreach ($invoices as $invoice) {
            if (!$invoice->uid) {
                \Modules\Finance\Models\Invoice::where('id', $invoice->id)
                    ->update([
                        'uid' => \Illuminate\Support\Str::orderedUuid()
                    ]);

                $count++;
            }
        }

        $this->info("{$count} uid updated successfully");
    }
}
