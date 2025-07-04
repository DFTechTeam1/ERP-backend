<?php

namespace Modules\Finance\Services;

use App\Actions\Finance\GenerateInvoiceContent;
use App\Enums\Transaction\InvoiceStatus;
use App\Services\GeneralService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Repository\InvoiceRepository;
use Modules\Finance\Repository\TransactionRepository;
use Modules\Production\Repository\ProjectDealRepository;

class InvoiceService {
    private $repo;

    private $projectDealRepo;

    private $generalService;

    private $transactionRepo;

    /**
     * Construction Data
     */
    public function __construct(
        InvoiceRepository $repo,
        ProjectDealRepository $projectDealRepo,
        GeneralService $generalService,
        TransactionRepository $transactionRepo
    )
    {
        $this->repo = $repo;

        $this->projectDealRepo = $projectDealRepo;

        $this->generalService = $generalService;

        $this->transactionRepo = $transactionRepo;
    }

    /**
     * Get list of data
     *
     * @param string $select
     * @param string $where
     * @param array $relation
     * 
     * @return array
     */
    public function list(
        string $select = '*',
        string $where = '',
        array $relation = []
    ): array
    {
        try {
            $itemsPerPage = request('itemsPerPage') ?? 2;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            if (!empty($search)) {
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

            // format response
            $paginated = collect((object) $paginated)->map(function ($item) {
                $uid = \Illuminate\Support\Facades\Crypt::encryptString($item->id);

                return [
                    'uid' => $uid,
                    'number' => $item->parent_number,
                    'sequence' => $item->sequence,
                    'amount' => "Rp" . number_format(num: $item->amount, decimal_separator: ','),
                    'paid_amount' => "Rp" . number_format(num: $item->paid_amount, decimal_separator: ','),
                    'status' => $item->status->label(),
                    'status_color' => $item->status->color(),
                ];
            });

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
     *
     * @param string $uid
     * @return array
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
     * Generate new invoice
     * 
     * If current project deal have unpaid invoice, return error
     *
     * @param array $data               With this following structure
     * - string $transaction_date
     * - string|int $amount
     * 
     * @param string $projectDealUid
     * @param string $type              Type will be 'bill', 'current' or 'general'
     * 
     * @return array
     */
    public function store(array $data, string $projectDealUid): array
    {
        try {
            // generate invoice to bill to customer
            $paymentDate = $data['transaction_date'];
            $paymentDue = now()->parse($paymentDate)->addDays(7)->format('Y-m-d');
            $projectDealId = Crypt::decryptString($projectDealUid);

            // get project deal data
            $projectDeal = $this->projectDealRepo->show(uid: $projectDealId, select: 'id,customer_id,identifier_number,led_detail,country_id,state_id,city_id,name,venue,project_date,is_fully_paid', relation: [
                'transactions',
                'finalQuotation',
                'unpaidInvoice:id,project_deal_id'
            ]);

            if ($projectDeal->unpaidInvoice) {
                return errorResponse(message: __('notification.cannotCreateInvoiceIfYouHaveAnotherUnpaidInovice'));
            }

            // get invoice parent
            $invoiceParent = $this->repo->show(uid: 'uid', select: 'id,number,project_deal_id', where: "project_deal_id = {$projectDeal->id} AND is_main = 1");
            $lastInvoice = $invoiceParent->getLastInvoice();
            
            $nextSequence = $lastInvoice->sequence + 1;

            // define next suffix invoice
            $suffix = chr(64 + $nextSequence);
            $invoiceNumber = "{$invoiceParent->number} {$suffix}";

            // generate invoice content
            $invoiceContent = GenerateInvoiceContent::run(deal: $projectDeal, amount: $data['amount'], invoiceNumber: $invoiceNumber, requestDate: $paymentDate);

            $payload = [
                'amount' => $data['amount'],
                'paid_amount' => 0,
                'payment_due' => $paymentDue,
                'payment_date' => $paymentDate,
                'project_deal_id' => $projectDealId,
                'customer_id' => $projectDeal->customer_id,
                'status' => InvoiceStatus::Unpaid,
                'raw_data' => $invoiceContent,
                'parent_number' => $invoiceParent->number,
                'number' => $invoiceNumber,
                'is_main' => false,
                'sequence' => $nextSequence,
            ];

            $invoice = $this->repo->store($payload);

            // generate url with expired time
            $url = \Illuminate\Support\Facades\URL::signedRoute(
                name: 'invoice.download',
                parameters: [
                    'i' => $projectDealUid,
                    'n' => \Illuminate\Support\Facades\Crypt::encryptString($invoice->id)
                ],
                expiration: now()->addMinutes(5)
            );

            return generalResponse(
                message: 'success',
                data: [
                    'url' => $url
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update selected data
     *
     * @param array $data
     * @param string $id
     * @param string $where
     * 
     * @return array
     */
    public function update(
        array $data,
        string $id,
        string $where = ''
    ): array
    {
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
     * @param integer $id
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
     *
     * @param array $ids
     * 
     * @return array
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

    /**
     * Download the invoice based on invoice id
     * 
     * @return Response
     */
    public function downloadInvoice(): Response
    {
        $invoiceId = \Illuminate\Support\Facades\Crypt::decryptString(request('n'));
        $invoice = $this->repo->show(uid: $invoiceId, select: 'id,raw_data,parent_number,number,sequence,project_deal_id', relation: [
            'projectDeal:id,name,project_date,customer_id',
            'projectDeal.customer:id,name'
        ]);

        // only get the parent number 
        $invoiceNumber = $invoice->sequence == 0 ? $invoice->number : $invoice->parent_number;

        // replace '\' or '/' to avoid error in the file name
        $invoiceNumber = str_replace(['/', '\/'], ' ', $invoiceNumber);
 
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView("invoices.invoice", $invoice->raw_data)
            ->setPaper('A4')
            ->setOption([
                'isPhpEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'debugPng' => false,
                'debugLayout' => false,
                'debugCss' => false
            ]);

        $filename = "Inv {$invoiceNumber} - {$invoice->projectDeal->customer->name} - {$invoice->projectDeal->project_date}.pdf";

        return $pdf->download(filename: $filename);
    }
}