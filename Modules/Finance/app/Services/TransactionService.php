<?php

namespace Modules\Finance\Services;

use App\Enums\ErrorCode\Code;
use App\Services\GeneralService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Finance\Models\TransactionImage;
use Modules\Finance\Repository\TransactionRepository;
use Modules\Production\Repository\ProjectDealRepository;
use Modules\Production\Repository\ProjectQuotationRepository;

class TransactionService {
    private $repo;

    private $projectQuotationRepo;

    private $generalService;

    private $projectDealRepo;

    /**
     * Construction Data
     */
    public function __construct(
        TransactionRepository $repo,
        ProjectQuotationRepository $projectQuotationRepo,
        GeneralService $generalService,
        ProjectDealRepository $projectDealRepo
    )
    {
        $this->repo = $repo;

        $this->projectQuotationRepo = $projectQuotationRepo;

        $this->generalService = $generalService;

        $this->projectDealRepo = $projectDealRepo;
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
     * Create customer transaction
     *
     * @param array $data           With this following structure
     * - string|float $payment_amount
     * - string $transaction_date
     * - ?string $note
     * - ?string $reference
     * - array $images              With this following structure
     *      - ?object|binary $image
     * @param string $quotationId
     * 
     * @return array
     */
    public function store(array $data, string $quotationId, string $projectDealUid): array
    {
        DB::beginTransaction();
        $tmp = [];
        try {
            $quotationId = \Illuminate\Support\Facades\Crypt::decryptString($quotationId);
            
            // get customer
            $quotation = $this->projectQuotationRepo->show(
                uid: 'id',
                select: 'id,project_deal_id,is_final',
                relation: [
                    'deal:id,customer_id,is_fully_paid',
                    'deal.transactions:id,project_deal_id,payment_amount',
                    'deal.finalQuotation:id,project_deal_id,fix_price'
                ],
                where: "quotation_id = '{$quotationId}'"
            );

            if (!$quotation) {
                return errorResponse(__('notification.quotationNotFound'));
            }

            if (!$quotation->is_final) {
                return errorResponse(__('notification.quotationIsNotFinal'));
            }

            // validate amount
            $remainingPayment = $quotation->deal->getRemainingPayment();
            if ($remainingPayment < $data['payment_amount']) {
                return errorResponse(__('notification.paymentAmountShouldBeSmallerThanRemainingAmount'));
            }

            $data['customer_id'] = $quotation->deal->customer_id;
            $data['project_deal_id'] = $quotation->deal->id;

            $trx = $this->repo->store(
                collect($data)->except(['images'])->toArray()
            );

            $payloadImage = [];
            if (isset($data['images'])) {
                foreach ($data['images'] as $image) {
                    $imageName = $this->generalService->uploadImageandCompress(
                        path: 'transactions',
                        image: $image['image'],
                        compressValue: 1
                    );
    
                    $tmp[] = $imageName;
    
                    $payloadImage[] = [
                        'image' => $imageName
                    ];
                }
            }

            $trx->attachments()->createMany($payloadImage);

            // define fully paid
            if ($remainingPayment == $data['payment_amount']) {
                // updata parent
                $this->projectDealRepo->update(
                    data: [
                        'is_fully_paid' => 1
                    ],
                    id: $quotation->deal->id
                );
            }

            DB::commit();

            return generalResponse(
                message: __('notification.successCreateTransaction')
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            // delete image
            if (count($tmp) > 0) {
                foreach ($tmp as $tmpFile) {
                    if (Storage::exists('transactions/' . $tmpFile)) {
                        Storage::delete('transactions/' . $tmpFile);
                    }
                }
            }

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
}