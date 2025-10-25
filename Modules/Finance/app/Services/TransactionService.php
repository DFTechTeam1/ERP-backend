<?php

namespace Modules\Finance\Services;

use App\Enums\Transaction\TransactionType;
use App\Services\GeneralService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Finance\Jobs\TransactionCreatedJob;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Repository\InvoiceRepository;
use Modules\Finance\Repository\TransactionRepository;
use Modules\Production\Repository\ProjectDealRepository;
use Modules\Production\Repository\ProjectQuotationRepository;

class TransactionService
{
    private $repo;

    private $projectQuotationRepo;

    private $generalService;

    private $projectDealRepo;

    private $invoiceRepo;

    /**
     * Construction Data
     */
    public function __construct(
        TransactionRepository $repo,
        ProjectQuotationRepository $projectQuotationRepo,
        GeneralService $generalService,
        ProjectDealRepository $projectDealRepo,
        InvoiceRepository $invoiceRepo
    ) {
        $this->repo = $repo;

        $this->projectQuotationRepo = $projectQuotationRepo;

        $this->generalService = $generalService;

        $this->projectDealRepo = $projectDealRepo;

        $this->invoiceRepo = $invoiceRepo;
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

            $whereHas = [];

            $paginated = $this->repo->pagination(
                select: $select,
                where: $where,
                relation: $relation,
                itemsPerPage: $itemsPerPage,
                page: $page,
                whereHas: $whereHas
            );
            $paginated = $paginated->map(function ($item) {
                return [
                    'uid' => $item->uid,
                    'transaction_date' => date('d F Y', strtotime($item->transaction_date)),
                    'transaction_time' => '',
                    'source_name' => $item->sourceable ? $item->sourceable->getSourceName() : '-',
                    'source_type' => $item->sourceable_type == Invoice::class ? 'Invoice' : 'Refund',
                    'transaction_type' => $item->transaction_type->categoryType(),
                    'transaction_type_text' => $item->transaction_type->label(),
                    'amount' => $item->payment_amount,
                    'payment_method' => '',
                    'payment_method_text' => '-',
                    'debit_credit' => $item->debit_credit,
                ];
            });
            $totalData = $this->repo->list(select: 'id', where: $where, whereHas: $whereHas)->count();

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

    /**
     * Get transaction summary
     * @return array<string, mixed>
     */
    public function getTransactionSummary(): array
    {
        try {
            $totalIncomeInCurrentMonth = $this->repo->list(
                select: 'SUM(payment_amount) as total_income',
                where: "debit_credit = 'debit' AND MONTH(transaction_date) = MONTH(CURRENT_DATE()) AND YEAR(transaction_date) = YEAR(CURRENT_DATE())"
            )->first()->total_income ?? 0;

            $totalOutcomeInCurrentMonth = $this->repo->list(
                select: 'SUM(payment_amount) as total_outcome',
                where: "debit_credit = 'credit' AND MONTH(transaction_date) = MONTH(CURRENT_DATE()) AND YEAR(transaction_date) = YEAR(CURRENT_DATE())"
            )->first()->total_outcome ?? 0;

            $totalRefunds = $this->repo->list(
                select: 'SUM(payment_amount) as total_refunds',
                where: "transaction_type = '". TransactionType::Refund->value ."' AND MONTH(transaction_date) = MONTH(CURRENT_DATE()) AND YEAR(transaction_date) = YEAR(CURRENT_DATE())"
            )->first()->total_refunds ?? 0;

            $transactionCount = $this->repo->list(
                select: 'id',
                where: "MONTH(transaction_date) = MONTH(CURRENT_DATE()) AND YEAR(transaction_date) = YEAR(CURRENT_DATE())"
            )->count();

            $netAmount = $totalIncomeInCurrentMonth - $totalOutcomeInCurrentMonth - $totalRefunds;

            return generalResponse(
                message: 'Success',
                data: [
                    'total_income' => $totalIncomeInCurrentMonth,
                    'total_outcome' => $totalOutcomeInCurrentMonth,
                    'total_refunds' => $totalRefunds,
                    'transaction_count' => $transactionCount,
                    'net_amount' => $netAmount,
                    'total_payments' => 0
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function datatable()
    {
        try {
            return generalResponse(
                message: 'Success',
                data: [],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get detail data
     */
    public function show(string $uid): array
    {
        try {
            $data = $this->repo->show(uid: $uid, select: '*');
            $attachment = null;
            if ($data->attachments->isNotEmpty()) {
                $attachment = $data->attachments->first()->image;
                if ($data->transaction_type == TransactionType::Refund) {
                    $attachment = asset('storage/refunds/'.$attachment);
                } else {
                    $attachment = asset('storage/transactions/'.$attachment);
                }
            }
            $output = [
                'uid' => $data->uid,
                'transaction_code' => '',
                'transaction_date' => date('d F Y', strtotime($data->transaction_date)),
                'transaction_time' => '',
                'transaction_type' => $data->transaction_type->categoryType(),
                'transaction_type_text' => $data->transaction_type->label(),
                'debit_credit' => $data->debit_credit,
                'source_type' => $data->sourceable ? $data->sourceable->getSourceName() : '-',
                'source_name' => $data->sourceable_type == Invoice::class ? 'Invoice' : 'Refund',
                'source_id' => 'id',
                'account_number' => '',
                'reference_number' => $data->reference,
                'notes' => $data->note,
                'description' => '',
                'created_by_name' => '',
                'created_at' => date('d F Y H:i:s', strtotime($data->created_at)),
                'proof_of_payment' => $attachment,
            ];

            return generalResponse(
                'success',
                false,
                $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Create transaction based on invoice
     *
     * @param  array  $payload  With this following structure:
     *                          - string|int $payment_amount
     *                          - string $transaction_date
     *                          - string $invoice_id
     *                          - ?string $note
     *                          - ?string $reference
     *                          - array $images                              With this following structure:
     *                          - object $image
     */
    public function store(array $payload, string $projectDealUid): array
    {
        DB::beginTransaction();
        $tmp = [];
        try {
            $projectId = \Illuminate\Support\Facades\Crypt::decryptString($projectDealUid);

            $projectDeal = $this->projectDealRepo->show(
                uid: $projectId,
                select: 'id,customer_id,is_fully_paid,identifier_number',
                relation: [
                    'transactions:id,project_deal_id,payment_amount',
                    'finalQuotation:id,project_deal_id,fix_price',
                ]
            );

            $allPaid = false;

            // define transaction type
            if ($projectDeal->transactions->count() == 0) {
                $type = TransactionType::DownPayment;
            } elseif ($projectDeal->getRemainingPayment() == $payload['payment_amount']) {
                $type = TransactionType::Repayment;
                $allPaid = true;
            } else {
                $type = TransactionType::Credit;
            }

            // get invoice id based on invoice uid
            $invoiceUid = $payload['invoice_id'];
            $invoiceId = $this->generalService->getIdFromUid($payload['invoice_id'], new Invoice);

            $payload['project_deal_id'] = $projectDeal->id;
            $payload['customer_id'] = $projectDeal->customer_id;
            $payload['transaction_type'] = $type;
            $payload['invoice_id'] = $invoiceId;
            $payload['trx_id'] = "TRX - {$projectDeal->identifier_number} - ".now()->format('Y');
            $payload['sourceable_type'] = Invoice::class;
            $payload['sourceable_id'] = $invoiceId;

            $trx = $this->repo->store(
                collect($payload)->except(['images'])->toArray()
            );

            $payloadImage = [];
            if (isset($payload['images'])) {
                foreach ($payload['images'] as $image) {
                    $imageName = $this->generalService->uploadImageandCompress(
                        path: 'transactions',
                        image: $image['image'],
                        compressValue: 1
                    );

                    if (! $imageName) {
                        DB::rollBack();

                        return errorResponse(message: 'Failed to process transaction');
                    }

                    $tmp[] = $imageName;

                    $payloadImage[] = [
                        'image' => $imageName,
                    ];
                }
            }

            $trx->attachments()->createMany($payloadImage);

            // here we will update the invoice content. We update the transactions content there
            $invoice = $this->invoiceRepo->show(uid: $invoiceUid, select: 'id,raw_data');
            $rawData = $invoice->raw_data;

            $currentTransactions = $rawData['transactions'];
            $currentTransactions = collect($currentTransactions)->map(function ($transaction) use ($trx) {
                if ($transaction['id'] == null) {
                    $transaction['id'] = $trx->id;
                    $transaction['transaction_date'] = date('d F Y', strtotime($trx->transaction_date));
                }

                return $transaction;
            })->toArray();

            $rawData['transactions'] = $currentTransactions;
            $this->invoiceRepo->update(data: [
                'raw_data' => $rawData,
            ], id: $invoiceUid);

            // update project deal data when all invoice has been paid
            if ($allPaid) {
                $this->projectDealRepo->update(data: ['is_fully_paid' => 1], id: $projectId);
            }

            // send notifications
            TransactionCreatedJob::dispatch($trx->id)->afterCommit();

            DB::commit();

            return generalResponse(
                message: 'Success',
                data: []
            );
        } catch (\Throwable $th) {
            // delete image
            if (count($tmp) > 0) {
                foreach ($tmp as $tmpFile) {
                    if (Storage::exists('transactions/'.$tmpFile)) {
                        Storage::delete('transactions/'.$tmpFile);
                    }
                }
            }

            DB::rollBack();

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
                    'size' => $item['textDetail'],
                ];
            })->toArray();
        }

        if (isset($ledDetail['prefunction'])) {
            $prefunction = collect($ledDetail['prefunction'])->map(function ($item) {
                return [
                    'name' => 'Prefunction',
                    'total' => $item['totalRaw'],
                    'size' => $item['textDetail'],
                ];
            })->toArray();
        }
    }

    /**
     * Generated signed url invoice
     *
     * @param  array  $payload  With this following structure:
     *                          - string $uid
     *                          - string $type (bill or current)
     *                          - string $amount
     *                          - string $date
     *                          - string $output (stream or download)
     */
    public function downloadInvoice(array $payload): array
    {
        return [];
    }

    protected function generateInvoice(int $id, array $payload, string $filepath, string $cacheKey): string {
        return '';
    }
}
