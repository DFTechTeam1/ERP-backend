<?php

namespace Modules\Production\Services;

use App\Services\GeneralService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Modules\Finance\Repository\TransactionRepository;
use Modules\Production\Repository\ProjectDealRepository;
use Modules\Production\Repository\ProjectQuotationRepository;

class ProjectQuotationService
{
    private $repo;

    private $generalService;

    private $transactionRepo;

    private $projectDealRepo;

    /**
     * Construction Data
     */
    public function __construct(
        ProjectQuotationRepository $repo,
        \App\Services\GeneralService $generalService,
        TransactionRepository $transactionRepo,
        ProjectDealRepository $projectDealRepo,
    )
    {
        $this->repo = $repo;

        $this->generalService = $generalService;

        $this->transactionRepo = $transactionRepo;

        $this->projectDealRepo = $projectDealRepo;
    }

    /**
     * Get list of data
     */
    public function list(
        string $select = '*',
        string $where = '',
        array $relation = []
    ): array {
        try {
            $itemsPerPage = request('itemsPerPage') ?? 2;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            if (! empty($search)) {
                $where = "lower(name) LIKE '%{$search}%'";
            }

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page
            );
            $totalData = $this->repo->list('id', $where)->count();

            return generalResponse(
                'Success',
                false,
                [
                    'paginated' => $paginated,
                    'totalData' => $totalData,
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function datatable()
    {
        //
    }

    /**
     * Get detail data
     */
    public function show(string $uid): array
    {
        try {
            $data = $this->repo->show($uid, 'name,uid,id');

            return generalResponse(
                'success',
                false,
                $data->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Store data
     */
    public function store(array $data): array
    {
        try {
            $this->repo->store($data);

            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update selected data
     */
    public function update(
        array $data,
        string $id,
        string $where = ''
    ): array {
        try {
            $this->repo->update($data, $id);

            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete selected data
     *
     *
     * @return void
     */
    public function delete(int $id): array
    {
        try {
            return generalResponse(
                'Success',
                false,
                $this->repo->delete($id)->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete bulk data
     */
    public function bulkDelete(array $ids): array
    {
        try {
            $this->repo->bulkDelete($ids, 'uid');

            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function generateQuotation(string $quotationId, string $type): Response
    {
        $quotationId = Crypt::decryptString($quotationId);
        $data = $this->repo->show(
            uid: 'uid',
            select: 'id,project_deal_id,fix_price,quotation_id,description,design_job',
            relation: [
                'deal:id,name,project_date,customer_id,event_type,venue,collaboration,led_detail,country_id,state_id,city_id,project_class_id',
                'deal.city:id,name',
                'deal.country:id,name',
                'deal.state:id,name',
                'deal.customer:id,name',
                'deal.class:id,name',
                'deal.marketings:id,employee_id,project_deal_id',
                'deal.marketings.employee:id,name',
                'items:quotation_id,id,item_id',
                'items.item:id,name'
            ],
            where: "quotation_id = '{$quotationId}'"
        );

        $output = [
            'rules' => $this->generalService->getSettingByKey('quotation_rules'),
            'company' => [
                'address' => $this->generalService->getSettingByKey('company_address'),
                'email' => $this->generalService->getSettingByKey('company_email'),
                'phone' => $this->generalService->getSettingByKey('company_phone'),
                'name' => $this->generalService->getSettingByKey('company_name'),
            ],
            'quotationNumber' => "{$quotationId}",
            'date' => date('d F Y', strtotime($data->deal->project_date)),
            'designJob' => $data->design_job,
            'price' => 'Rp' . number_format(num: $data->fix_price, decimal_separator: ','),
            'client' => [
                'name' => $data->deal->customer->name,
                'city' => $data->deal->city->name,
                'country' => $data->deal->country->name
            ],
            'event' => [
                'title' => $data->deal->name,
                'date' => date('d F Y', strtotime($data->deal->project_date)),
                'venue' => $data->deal->venue
            ],
            'ledDetails' => collect($data->deal->led_detail)->map(function ($item) {
                return [
                    'name' => $item['name'] == 'main' ? 'Main Stage' : 'Prefunction',
                    'size' => $item['textDetail']
                ];
            })->toArray(),
            'marketing' => [
                'name' => $data->deal->marketings[0]->employee->name,
            ],
            'items' => collect($data->items)->map(function ($item) {
                return $item->item->name;
            })->toArray()
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView("quotation.quotation-lato", $output)
        ->setPaper('14')
        ->setOption([
            'isPhpEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'debugPng' => false,
            'debugLayout' => false,
            'debugCss' => false
        ]);

        $filename = "Quot {$data->quotation_id} - {$data->deal->customer->name} - {$data->deal->project_date}.pdf";

        if ($type == 'stream') {
            return $pdf->stream($filename);
        } else {
            return $pdf->download($filename);
        }
    }

    public function generateInvoiceFromDeal(string $projectDealUid, string $type): Response
    {
        $requestAmount = request('amount');
        $requestDate = request('date');
        $id = Crypt::decryptString($projectDealUid);

        $deal = $this->projectDealRepo->show(
            uid: $id,
            select: 'id,name,project_date,led_detail,venue,city_id,country_id,is_fully_paid,customer_id',
            relation: [
                'transactions',
                'finalQuotation:id,project_deal_id,main_ballroom,prefunction,high_season_fee,fix_price',
                'finalQuotation.items:id,quotation_id,item_id',
                'finalQuotation.items.item:id,name',
                'transactions',
                'city:name,id',
                'country:name,id',
                'customer:id,name'
            ]
        );

        $projectDate = $deal->project_date;
        $month = MonthInBahasa(search: date('m', strtotime($projectDate)));
        $year = date('Y', strtotime($projectDate));
        $date = date('d', strtotime($projectDate));

        $main = [];
        $prefunction = [];

        $invoiceNumber = $this->generalService->generateInvoiceNumber();

        // call magic method
        $this->setProjectLed(main: $main, prefunction: $prefunction, ledDetailData: $deal->led_detail);

        $payload = [
            'projectName' => $deal->name,
            'projectDate' => "{$date} {$month} {$year}",
            'venue' => $deal->venue,
            'payment' => "Rp" . number_format(num: $requestAmount, decimal_separator: ','),
            'customer' => [
                'name' => $deal->customer->name,
                'city' => $deal->city->name,
                'country' => $deal->country->name,
            ],
            'invoiceNumber' => $invoiceNumber,
            'trxDate' => date('d F Y', strtotime($requestDate)),
            'paymentDue' => now()->parse($deal->project_date)->subDays(3)->format('d F Y'),
            'led' => [
                'main' => $main,
                'prefunction' => $prefunction
            ],
            'items' => collect($deal->finalQuotation->items)->pluck('item.name')->toArray(),
            'remainingPayment' => $deal->getRemainingPayment(formatPrice: true)
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView("invoices.invoice", $payload)
        ->setPaper('A4')
        ->setOption([
            'isPhpEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'debugPng' => false,
            'debugLayout' => false,
            'debugCss' => false
        ]);

        $filename = "Inv {$invoiceNumber} - {$deal->customer->name} - {$deal->project_date}.pdf";

        if ($type == 'stream') {
            return $pdf->stream($filename);
        } else {
            return $pdf->download($filename);
        }
    }

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

    public function generateInvoice(string $transactionUid, string $type): Response
    {
        $requestAmount = request('amount');

        // get detail transactions and detail of related quotation + quotation item
        $transaction = $this->transactionRepo->show(
            uid: $transactionUid,
            select: 'id,uid,project_deal_id,customer_id,payment_amount,reference,note,trx_id,transaction_date',
            relation: [
                'projectDeal:id,name,project_date,led_detail,venue,city_id,country_id,is_fully_paid',
                'projectDeal.finalQuotation:id,project_deal_id,main_ballroom,prefunction,high_season_fee,fix_price',
                'projectDeal.transactions',
                'projectDeal.city:name,id',
                'projectDeal.country:name,id',
                'projectDeal.finalQuotation.items:id,quotation_id,item_id',
                'projectDeal.finalQuotation.items.item:id,name',
                'customer:id,name'
            ]
        );

        $projectDate = $transaction->projectDeal->project_date;
        $month = MonthInBahasa(search: date('m', strtotime($projectDate)));
        $year = date('Y', strtotime($projectDate));
        $date = date('d', strtotime($projectDate));

        $ledDetail = collect($transaction->projectDeal->led_detail)->groupBy('name');
        $main = [];
        $prefunction = [];

        // call magic method
        $this->setProjectLed(main: $main, prefunction: $prefunction, ledDetailData: $transaction->projectDeal->led_detail);

        if ($requestAmount) {
            $paymentAmount = "Rp" . number_format(num: $requestAmount, decimal_separator: ',');
        } else {
            $paymentAmount = "Rp" . number_format(num: $transaction->payment_amount, decimal_separator: ',');
        }

        $invoiceNumber = $this->generalService->generateInvoiceNumber();

        $payload = [
            'projectName' => $transaction->projectDeal->name,
            'projectDate' => "{$date} {$month} {$year}",
            'venue' => $transaction->projectDeal->venue,
            'payment' => $paymentAmount,
            'customer' => [
                'name' => $transaction->customer->name,
                'city' => $transaction->projectDeal->city->name,
                'country' => $transaction->projectDeal->country->name,
            ],
            'invoiceNumber' => $transaction->trx_id,
            'trxDate' => date('d F Y', strtotime($transaction->transaction_date)),
            'paymentDue' => now()->parse($transaction->projectDeal->project_date)->subDays(3)->format('d F Y'),
            'led' => [
                'main' => $main,
                'prefunction' => $prefunction
            ],
            'items' => collect($transaction->projectDeal->finalQuotation->items)->pluck('item.name')->toArray(),
            'remainingPayment' => $transaction->projectDeal->getRemainingPayment(formatPrice: true)
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView("invoices.invoice", $payload)
        ->setPaper('A4')
        ->setOption([
            'isPhpEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'debugPng' => false,
            'debugLayout' => false,
            'debugCss' => false
        ]);

        $filename = "Inv {$invoiceNumber} - {$transaction->customer->name} - {$transaction->projectDeal->project_date}.pdf";

        if ($type == 'stream') {
            return $pdf->stream($filename);
        } else {
            return $pdf->download($filename);
        }
    }
}
