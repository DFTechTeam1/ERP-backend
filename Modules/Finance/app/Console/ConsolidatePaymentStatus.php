<?php

namespace Modules\Finance\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectQuotation;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ConsolidatePaymentStatus extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'finance:consolidate-payment {--apply}';

    /**
     * The console command description.
     */
    protected $description = 'Consolidate payment status in every payment deals';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $projects = $this->getProjectDeals();

        $checkList = [
            'DF01105',
            'DF01101',
            'DF01309',
            'DF01366',
            'DF01370',
            'DF01414',
            'DF01448',
            'DF01466',
            'DF01525',
            'DF01546',
            'DF01494',
            'DF01558',
        ];

        $needToUpdate = [];
        $body = [];
        $updateFullyPaidData = [];
        $updateQuotationPriceData = [];
        foreach ($projects as $project) {
            $invoiceTotal = $project->invoices->sum('paid_amount');
            $totalProjectDeal = $project->finalQuotation->fix_price;
            $transactionAmount = $project->transactions->sum('payment_amount');

            $isFullyPaidViaTransaction = $invoiceTotal == 0 && $transactionAmount > 0 && $transactionAmount == $totalProjectDeal ? true : false;
            $paidVia = $invoiceTotal == 0 && $transactionAmount > 0 ? 'migration transaction' : 'website via invoice';

            $issue = '-';
            $updateFullyPaid = false;
            $updateQuotationPrice = false;
            // getProjectDeals() already scopes to is_fully_paid = 0, so only the amount match matters here.
            if ($totalProjectDeal == $transactionAmount) {
                $issue = 'Need to update is fully paid status';
                $updateFullyPaid = true;
            }
            if ($project->finalQuotation->fix_price == 0 && $transactionAmount > 0) {
                $issue = 'Need to update quotation amount';
                $updateQuotationPrice = true;
            }

            if ($invoiceTotal == $totalProjectDeal) {
                $needToUpdate[] = [
                    'project_deal_id' => $project->id,
                    'fix_price' => $project->finalQuotation->fix_price,
                    'invoice_paid_amount' => $invoiceTotal,
                    'transaction_amount' => $transactionAmount,
                    'name' => $project->name,
                ];
            }

            if (in_array($project->finalQuotation->quotation_id, $checkList)) {
                if ($updateFullyPaid) {
                    $updateFullyPaidData[] = $project->id;
                }
                if ($updateQuotationPrice) {
                    $updateQuotationPriceData[] = [
                        'id' => $project->id,
                        'amount' => $transactionAmount,
                    ];
                }
                $body[] = [
                    $project->id,
                    $project->name,
                    $project->project_date,
                    $project->finalQuotation->quotation_id,
                    number_format($project->finalQuotation->fix_price),
                    number_format($transactionAmount),
                    $paidVia,
                    $transactionAmount == $totalProjectDeal ? 'Yes' : 'No',
                    $project->is_fully_paid,
                    $project->getStatusPayment(),
                    $issue,
                ];
            }
        }

        if ($this->option('apply')) {
            DB::transaction(function () use ($updateQuotationPriceData, $updateFullyPaidData) {
                if (! empty($updateFullyPaidData)) {
                    ProjectDeal::whereIn('id', $updateFullyPaidData)
                        ->update(['is_fully_paid' => 1]);
                    $this->info(count($updateFullyPaidData)." projects updated successfully\n");
                }

                if (! empty($updateQuotationPriceData)) {
                    foreach ($updateQuotationPriceData as $dataUpdate) {
                        ProjectQuotation::where('project_deal_id', $dataUpdate['id'])
                            ->update(['fix_price' => $dataUpdate['amount']]);
                    }
                    ProjectDeal::whereIn('id', collect($updateQuotationPriceData)->pluck('id')->toArray())
                        ->update([
                            'is_fully_paid' => 1,
                        ]);

                    $this->info(count($updateQuotationPriceData).' missing quotation prices updated');
                }
            });
            exit;
        }

        $this->table(
            ['id', 'name', 'project date', 'quotation id', 'quotation price', 'paid amount', 'paid via', 'should be fully paid?', 'is fully paid?', 'current status', 'issue'],
            $body
        );
    }

    protected function getProjectDeals()
    {
        // Only get payment deals that have invoices and is_fully_paid is false
        return ProjectDeal::query()
            ->where('is_fully_paid', 0)
            ->has('invoices')
            ->has('finalQuotation')
            ->with(['invoices', 'finalQuotation', 'transactions'])
            ->get();
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['example', InputArgument::REQUIRED, 'An example argument.'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null],
        ];
    }
}
