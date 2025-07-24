<?php

namespace Modules\Finance\Services;

use App\Actions\Finance\GenerateInvoiceContent;
use App\Enums\Finance\InvoiceRequestUpdateStatus;
use App\Enums\Transaction\InvoiceStatus;
use App\Models\User;
use App\Services\GeneralService;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Jobs\ApproveInvoiceChangesJob;
use Modules\Finance\Jobs\InvoiceHasBeenCreatedJob;
use Modules\Finance\Jobs\InvoiceHasBeenDeletedJob;
use Modules\Finance\Jobs\RequestInvoiceChangeJob;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Repository\InvoiceRepository;
use Modules\Finance\Repository\InvoiceRequestUpdateRepository;
use Modules\Finance\Repository\TransactionRepository;
use Modules\Production\Repository\ProjectDealRepository;

class InvoiceService {
    private $repo;

    private $projectDealRepo;

    private $generalService;

    private $transactionRepo;

    private $invoiceRequestUpdateRepo;

    /**
     * Construction Data
     */
    public function __construct(
        InvoiceRepository $repo,
        ProjectDealRepository $projectDealRepo,
        GeneralService $generalService,
        TransactionRepository $transactionRepo,
        InvoiceRequestUpdateRepository $invoiceRequestUpdateRepo
    )
    {
        $this->repo = $repo;

        $this->projectDealRepo = $projectDealRepo;

        $this->generalService = $generalService;

        $this->transactionRepo = $transactionRepo;

        $this->invoiceRequestUpdateRepo = $invoiceRequestUpdateRepo;
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
                'unpaidInvoice:id,project_deal_id',
                'invoices:id,project_deal_id'
            ]);
            $currentInvoiceCount = $projectDeal->invoices->count();

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
            $paramSignedRoute = [
                'i' => $projectDealUid,
                'n' => \Illuminate\Support\Facades\Crypt::encryptString($invoice->id),
                'additional' => 1,
                'am' => $data['amount'],
                'rd' => $paymentDate,
            ];
            if ($currentInvoiceCount == 0) {
                $paramSignedRoute['t'] = 'downPayment';
            }
            
            $url = \Illuminate\Support\Facades\URL::signedRoute(
                name: 'invoice.download',
                parameters: $paramSignedRoute,
                expiration: now()->addMinutes(5)
            );

            // running notifications
            InvoiceHasBeenCreatedJob::dispatch($invoice->uid);

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
     * Here we save temporary data for invoice update.
     * Need Director approval to change the invoice.
     *
     * @param array $payload
     * @param integer $invoiceId
     * @return array
     */
    public function updateTemporaryData(array $payload): array
    {
        DB::beginTransaction();
        try {
            $invoiceId = $this->generalService->getIdFromUid($payload['invoice_uid'], new Invoice());
            $payload['invoice_id'] = $invoiceId;
            $payload['status'] = InvoiceRequestUpdateStatus::Pending->value;
            $updateData = $this->invoiceRequestUpdateRepo->store(data: $payload);

            // update invoice status
            $this->repo->update(data: [
                'status' => InvoiceStatus::WaitingChangesApproval
            ], id: $payload['invoice_uid']);

            // send notification to director
            RequestInvoiceChangeJob::dispatch($updateData);

            DB::commit();

            return generalResponse(
                message: __('notification.successRequestInvoiceChanges')
            );
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorResponse($th);
        }
    }

    /**
     * Approve changes for invoice
     * 
     * @param string $invoiceUid
     * 
     * @return array
     */
    public function approveChanges(string $invoiceUid, bool $fromExternalUrl = false): array
    {
        DB::beginTransaction();
        try {
            $invoiceId = $this->generalService->getIdFromUid($invoiceUid, new Invoice());

            $currentChanges = $this->invoiceRequestUpdateRepo->show(uid: 'id', select: 'amount,payment_date,id', where: "invoice_id = {$invoiceId} and status = " . InvoiceRequestUpdateStatus::Pending->value, relation: [
                'invoice:id,parent_number,number,sequence,raw_data',
                'user:id,email,employee_id',
                'user.employee:id,name'
            ]);

            // validate if there is no changes
            if (!$currentChanges) {
                return errorResponse(
                    message: __('notification.noChangesToApprove')
                );
            }

            $actorId = Auth::id();
            if ($fromExternalUrl) {
                $actorUid = request('dir');
                $actorId = $this->generalService->getIdFromUid($actorUid, new User());
            }

            $this->invoiceRequestUpdateRepo->update(
                data: [
                    'status' => InvoiceRequestUpdateStatus::Approved->value,
                    'approved_at' => Carbon::now(),
                    'approved_by' => $actorId,
                ],
                where: "invoice_id = {$invoiceId}"
            );

            // update the real invoice, we also update the raw_data column
            $invoice = $this->repo->show(uid: $invoiceUid, select: 'id,raw_data');
            $rawData = $invoice->raw_data;
            $rawDataFixPrice = str_replace(['Rp', ','], '', $rawData['fixPrice']);
            $transactions = $rawData['transactions'] ?? [];

            $payloadUpdate = [];

            if ($currentChanges->amount) {
                $payloadUpdate['amount'] = $currentChanges->amount;
                
                // set remaining payment
                $remainingPayment = $rawDataFixPrice - $currentChanges->amount;
                $rawData['remainingPayment'] = "Rp" . number_format(num: $remainingPayment, decimal_separator: ',');

                // update latest transaction item if transactions exists
                if (count($transactions) > 0) {
                    $lastTransaction = end($transactions);
                    $lastTransaction['payment'] = "Rp" . number_format(num: $currentChanges->amount, decimal_separator: ',');

                    // replace old latest transaction with new one
                    $transactions[count($transactions) - 1] = $lastTransaction;
                }
            }
            if ($currentChanges->payment_date) {
                $paymentDate = date('Y-m-d', strtotime($currentChanges->payment_date));
                // set payment due for the next 7 days
                $payloadUpdate['payment_due'] = now()->parse($paymentDate)->addDays(7)->format('Y-m-d');
                $payloadUpdate['payment_date'] = $paymentDate;
                $payloadUpdate['payment_due'] = $paymentDate;

                $rawData['paymentDue'] = date('d F Y', strtotime($payloadUpdate['payment_due']));
                $rawData['trxDate'] = date('d F Y', strtotime($paymentDate));

                // update transaction_date in latest transaction if transactions exists
                if (count($transactions) > 0) {
                    $lastTransaction = end($transactions);
                    $lastTransaction['transaction_date'] = date('d F Y', strtotime($paymentDate));
                    $transactions[count($transactions) - 1] = $lastTransaction;
                }
            }
            $rawData['transactions'] = $transactions;

            $payloadUpdate['raw_data'] = $rawData;

            $this->repo->update(data: $payloadUpdate, id: $invoiceUid);

            ApproveInvoiceChangesJob::dispatch($currentChanges->id)->afterCommit();

            DB::commit();
            
            return generalResponse(
                message: "Success"
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Update selected data
     * 
     * Here we will update in main table which is invoices table, then update invoice content in the raw_date column
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
     * @param string $invoiceUid
     * 
     * @return array
     */
    public function delete(string $invoiceUid): array
    {
        try {
            $invoice = $this->repo->show(
                uid: $invoiceUid,
                select: "id,uid,parent_number,project_deal_id,status",
                relation: [
                    'projectDeal:id,name'
                ]
            );

            if ($invoice->status == InvoiceStatus::Paid) {
                return errorResponse(message: __('notification.cannotDeletePaidInvoice'));
            }

            $parentNumber = $invoice->parent_number;
            $projectName = $invoice->projectDeal->name;

            $user = Auth::user();

            $this->repo->delete(invoiceUid: $invoiceUid);
            
            InvoiceHasBeenDeletedJob::dispatch($parentNumber, $projectName, $user);

            return generalResponse(
                __('notification.successDeleteInvoice'),
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
     */
    public function downloadInvoice()
    {
        $invoiceType = request('t'); // will be 'downPayment' or ....... for now just downPayment is available

        $view = $invoiceType == 'downPayment' ? 'invoices.downPaymentInvoice' : 'invoices.invoice';

        $invoiceId = \Illuminate\Support\Facades\Crypt::decryptString(request('n'));
        $invoice = $this->repo->show(
            uid: $invoiceId,
            select: 'id,raw_data,parent_number,number,sequence,project_deal_id,amount,payment_date',
            relation: [
                'projectDeal:id,name,project_date,customer_id',
                'projectDeal.customer:id,name',
                'projectDeal.finalQuotation:id,project_deal_id,description',
                'projectDeal.transactions:id,payment_amount,transaction_date,project_deal_id',
            ]
        );

        $description = $invoice->projectDeal->finalQuotation->description;

        // only get the parent number 
        $invoiceNumber = $invoice->sequence == 0 ? $invoice->number : $invoice->parent_number;

        // replace '\' or '/' to avoid error in the file name
        $invoiceNumber = str_replace(['/', '\/'], ' ', $invoiceNumber);
        $rawData = $invoice->raw_data;
        $rawData['description'] = $description;

        // set the amount and transaction date based on user input when invoice type is downpayment
        if ($invoiceType == 'downPayment') {
            $rawData['amountRequest'] = "Rp" . number_format(num: $invoice->amount, decimal_separator: ',');
            $rawData['transactionDateRequest'] = date('d F Y', strtotime($invoice->payment_date));
            $rawData['transactions'] = [];
            $remaining = (float) $rawData['fixPrice'] - (float) $invoice->amount;
            $rawData['remainingPayment'] = "Rp" . number_format(num: $remaining, decimal_separator: ',');
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($view, $rawData)
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

    /**
     * Download general invoice based on invoice id
     * 
     * @return Response
     */
    public function downloadGeneralInvoice(): Response
    {
        $projectDealId = \Illuminate\Support\Facades\Crypt::decryptString(request('i'));
        $projectDeal = $this->projectDealRepo->show(
            uid: $projectDealId,
            select: 'id',
            relation: [
                'mainInvoice',
                'finalQuotation:id,project_deal_id,description'
            ]
        );

        // only get the parent number 
        $invoiceNumber = $projectDeal->mainInvoice->sequence == 0 ? $projectDeal->mainInvoice->number : $projectDeal->mainInvoice->parent_number;

        // reformat transaction and remaining payment
        $rawData = $projectDeal->mainInvoice->raw_data;
        $rawData['transactions'] = [];
        $rawData['remainingPayment'] = $rawData['fixPrice'];

        // insert quotation note
        $rawData['description'] = $projectDeal->finalQuotation->description;

        // replace '\' or '/' to avoid error in the file name
        $invoiceNumber = str_replace(['/', '\/'], ' ', $invoiceNumber);

        if (empty($rawData['invoiceNumber'])) {
            $rawData['invoiceNumber'] = $invoiceNumber;
        }
 
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView("invoices.invoice", $rawData)
            ->setPaper('A4')
            ->setOption([
                'isPhpEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'debugPng' => false,
                'debugLayout' => false,
                'debugCss' => false
            ]);

        $filename = "Inv {$invoiceNumber} - {$projectDeal->mainInvoice->projectDeal->customer->name} - {$projectDeal->mainInvoice->projectDeal->project_date}.pdf";

        return $pdf->download(filename: $filename);
    }
}