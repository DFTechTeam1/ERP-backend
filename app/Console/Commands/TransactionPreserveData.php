<?php

namespace App\Console\Commands;

use App\Actions\Finance\GenerateInvoiceContent;
use App\Enums\Transaction\InvoiceStatus;
use App\Enums\Transaction\TransactionType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\Transaction;

class TransactionPreserveData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:transaction-preserve-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Schema::disableForeignKeyConstraints();
        DB::table('invoices')->truncate();
        Schema::enableForeignKeyConstraints();

        $transactions = Transaction::with([
            'projectDeal',
            'projectDeal.finalQuotation',
            'projectDeal.transactions',
        ])
            ->get();

        // create invoice based on transaction data
        foreach ($transactions as $key => $transaction) {
            // create invoice parent first

            $generateInvoiceContent = GenerateInvoiceContent::run($transaction->projectDeal, $transaction->payment_amount, $transaction->trx_id, $transaction->created_at, true);

            $payloadParent = [
                'amount' => $transaction->payment_amount,
                'paid_amount' => $transaction->payment_amount,
                'payment_date' => $transaction->created_at,
                'payment_due' => now()->parse($transaction->transaction_date)->addDays(7)->format('Y-m-d'),
                'project_deal_id' => $transaction->project_deal_id,
                'customer_id' => $transaction->projectDeal->customer_id,
                'status' => InvoiceStatus::Paid->value,
                'raw_data' => $generateInvoiceContent,
                'parent_number' => null,
                'number' => $transaction->trx_id,
                'is_main' => 1,
                'sequence' => 0,
                'created_by' => $transaction->created_by,
            ];

            Invoice::create($payloadParent);

            // create real invoice
            $payload = [
                'amount' => $transaction->payment_amount,
                'paid_amount' => $transaction->payment_amount,
                'payment_date' => $transaction->created_at,
                'payment_due' => now()->parse($transaction->transaction_date)->addDays(7)->format('Y-m-d'),
                'project_deal_id' => $transaction->project_deal_id,
                'customer_id' => $transaction->projectDeal->customer_id,
                'status' => InvoiceStatus::Paid->value,
                'raw_data' => $generateInvoiceContent,
                'parent_number' => null,
                'number' => $transaction->trx_id.' '.chr(64 + $key + 1),
                'is_main' => 0,
                'sequence' => $key + 1,
                'created_by' => $transaction->created_by,
            ];

            $last = Invoice::create($payload);

            Transaction::where('id', $transaction->id)
                ->update([
                    'invoice_id' => $last->id,
                    'transaction_type' => TransactionType::DownPayment,
                ]);

            $this->info('Invoice updated');
        }
    }
}
