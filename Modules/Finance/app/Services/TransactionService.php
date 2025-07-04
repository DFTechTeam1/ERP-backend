<?php

namespace Modules\Finance\Services;

use App\Enums\ErrorCode\Code;
use App\Enums\Transaction\TransactionType;
use App\Services\GeneralService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Finance\Jobs\TransactionCreatedJob;
use Modules\Finance\Models\TransactionImage;
use Modules\Finance\Repository\InvoiceRepository;
use Modules\Finance\Repository\TransactionRepository;
use Modules\Production\Repository\ProjectDealRepository;
use Modules\Production\Repository\ProjectQuotationRepository;

class TransactionService {
    private $repo;

    private $projectQuotationRepo;

    private $generalService;

    private $projectDealRepo;

    private $inventoryRepo;

    /**
     * Construction Data
     */
    public function __construct(
        TransactionRepository $repo,
        ProjectQuotationRepository $projectQuotationRepo,
        GeneralService $generalService,
        ProjectDealRepository $projectDealRepo,
        InvoiceRepository $inventoryRepo
    )
    {
        $this->repo = $repo;

        $this->projectQuotationRepo = $projectQuotationRepo;

        $this->generalService = $generalService;

        $this->projectDealRepo = $projectDealRepo;

        $this->inventoryRepo = $inventoryRepo;
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
     * Create transaction based on invoice
     * 
     * @param array $payload                    With this following structure:
     * - string|int $payment_amount
     * - string $transaction_date
     * - string $invoice_id
     * - ?string $note
     * - ?string $reference
     * - array $images                              With this following structure:
     *      - object $image
     * @param string $projectDealUid
     * 
     * @return array
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
                    'finalQuotation:id,project_deal_id,fix_price'
                ]
            );

            $allPaid = false;

            // define transaction type
            if ($projectDeal->transactions->count() == 0) {
                $type = TransactionType::DownPayment;
            } else if ($projectDeal->getRemainingPayment() == $payload['payment_amount']) {
                $type = TransactionType::Repayment;
                $allPaid = true;
            } else {
                $type = TransactionType::Credit;   
            }

            $payload['project_deal_id'] = $projectDeal->id;
            $payload['customer_id'] = $projectDeal->customer_id;
            $payload['transaction_type'] = $type;
            $payload['invoice_id'] = \Illuminate\Support\Facades\Crypt::decryptString($payload['invoice_id']);
            $payload['trx_id'] = "TRX - {$projectDeal->identifier_number} - " . now()->format('Y');

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
                    
                    if (!$imageName) {
                        DB::rollBack();
                        return errorMessage(message: 'Failed to process transaction');
                    }
    
                    $tmp[] = $imageName;
    
                    $payloadImage[] = [
                        'image' => $imageName
                    ];
                }
            }

            $trx->attachments()->createMany($payloadImage);

            // update project deal data when all invoice has been paid
            if ($allPaid) {
                $this->projectDealRepo->update(data: ['is_fully_paid' => 1], id: $projectId);
            }

            // send notifications
            TransactionCreatedJob::dispatch($trx->id)->afterCommit();

            DB::commit();

            return generalResponse(
                message: "Success",
                data: []
            );
        } catch (\Throwable $th) {
            // delete image
            if (count($tmp) > 0) {
                foreach ($tmp as $tmpFile) {
                    if (Storage::exists('transactions/' . $tmpFile)) {
                        Storage::delete('transactions/' . $tmpFile);
                    }
                }
            }

            DB::rollBack();

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

    /**
     * Generated signed url invoice
     * 
     * @param array $payload                    With this following structure:
     * - string $uid
     * - string $type (bill or current)
     * - string $amount
     * - string $date
     * - string $output (stream or download)
     * 
     * @return array
     */
    public function downloadInvoice(array $payload): array
    {
        //
    }

    protected function generateInvoice(int $id, array $payload, string $filepath, string $cacheKey): string
    {
    }
}