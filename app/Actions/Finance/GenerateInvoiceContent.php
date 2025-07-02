<?php

namespace App\Actions\Finance;

use App\Services\GeneralService;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Models\ProjectDeal;

class GenerateInvoiceContent
{
    use AsAction;

    /**
     * Generate invoice content
     * 
     * @param ProjectDeal $deal
     * @param string|int $amount
     * @param string $invoiceNumber
     * @param string $requestDate
     * 
     * @return array
     */
    public function handle(ProjectDeal $deal, string|int $amount, string $invoiceNumber, string $requestDate)
    {
        $generalService = new GeneralService();

        $projectDate = $deal->project_date;
        $month = MonthInBahasa(search: date('m', strtotime($projectDate)));
        $year = date('Y', strtotime($projectDate));
        $date = date('d', strtotime($projectDate));

        // set transactions
        $transactions = $deal->transactions->map(function ($transaction) {
            return [
                'payment' => "Rp" . number_format(num: $transaction->payment_amount, decimal_separator: ','),
                'transaction_date' => date('d F Y', strtotime($transaction->transaction_date))
            ];
        })->toArray();

        if ($amount > 0) {
            $transactions = collect($transactions)->merge([
                'payment' => $amount,
                'transaction_date' => date('d F Y', strtotime($requestDate))
            ]);
        }

        $main = [];
        $prefunction = [];

        // call magic method
        $this->setProjectLed(main: $main, prefunction: $prefunction, ledDetailData: $deal->led_detail);

        $payload = [
            'projectName' => $deal->name,
            'projectDate' => "{$date} {$month} {$year}",
            'venue' => $deal->venue,
            'fixPrice' => "Rp" . number_format(num: $deal->finalQuotation->fix_price, decimal_separator: ','),
            'customer' => [
                'name' => $deal->customer->name,
                'city' => $deal->city->name,
                'country' => $deal->country->name,
            ],
            'transactions' => $transactions,
            'company' => [
                'address' => $generalService->getSettingByKey('company_address'),
                'email' => $generalService->getSettingByKey('company_email'),
                'phone' => $generalService->getSettingByKey('company_phone'),
                'name' => $generalService->getSettingByKey('company_name'),
            ],
            'invoiceNumber' => $invoiceNumber,
            'trxDate' => date('d F Y', strtotime($requestDate)),
            'paymentDue' => now()->parse($requestDate)->addDays(7)->format('d F Y'),
            'led' => [
                'main' => $main,
                'prefunction' => $prefunction
            ],
            'items' => collect($deal->finalQuotation->items)->pluck('item.name')->toArray(),
            'remainingPayment' => $deal->getRemainingPayment(formatPrice: true)
        ];

        return $payload;
    }

    /**
     * Set LED for invoice
     * 
     * @param array &$main
     * @param array &$prefunction
     * @param array $ledDetailData
     */
    protected function setProjectLed(array &$main, array &$prefunction, array $ledDetailData): void
    {
        $ledDetail = collect($ledDetailData)->groupBy('name');
        $main = [];
        $prefunction = [];

        if (isset($ledDetail['main'])) {
            $main = collect($ledDetail['main'])->map(function ($item) {
                return [
                    'name' => 'Main Stage',
                    'total' => $item['totalRaw'],
                    'size' => $item['textDetail']
                ];
            })->toArray();
        }

        if (isset($ledDetail['prefunction'])) {
            $prefunction = collect($ledDetail['prefunction'])->map(function ($item) {
                return [
                    'name' => 'Prefunction',
                    'total' => $item['totalRaw'],
                    'size' => $item['textDetail']
                ];
            })->toArray();
        }
    }
}
