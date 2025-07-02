<?php

namespace Modules\Finance\Services;

use App\Actions\Finance\GenerateInvoiceContent;
use App\Enums\ErrorCode\Code;
use App\Enums\Transaction\InvoiceStatus;
use App\Services\GeneralService;
use Illuminate\Support\Facades\Crypt;
use Modules\Finance\Repository\InvoiceRepository;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Repository\ProjectDealRepository;

class InvoiceService {
    private $repo;

    private $projectDealRepo;

    private $generalService;

    /**
     * Construction Data
     */
    public function __construct(
        InvoiceRepository $repo,
        ProjectDealRepository $projectDealRepo,
        GeneralService $generalService
    )
    {
        $this->repo = $repo;

        $this->projectDealRepo = $projectDealRepo;

        $this->generalService = $generalService;
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
     * Store data
     *
     * @param array $data
     * 
     * @return array
     */
    public function store(array $data, string $projectDealUid): array
    {
        try {
            // generate invoice to bill to customer
            $paymentDate = $data['transaction_date'];
            $paymentDue = now()->parse($paymentDate)->format('Y-m-d');
            $projectDealId = Crypt::decryptString($projectDealUid);

            // get project deal data
            $projectDeal = $this->projectDealRepo->show(uid: $projectDealId, select: 'id,customer_id,identifier_number');
            $identifierNumber = ltrim($projectDeal->identifier_number, 0);

            // get invoice parent
            $invoiceParent = $this->repo->show(uid: 'uid', select: 'id,number', where: "project_deal_id = {$projectDeal->id} AND is_main = 1");
            $lastInvoice = $invoiceParent->getLastChild();

            // generate invoice content
            $invoiceContent = GenerateInvoiceContent::run(deal: $projectDeal, amount: $data['amount'], invoiceNumber: '', requestDate: $paymentDate);

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
                'number' => $identifierNumber,
                'is_main' => false,
                'sequence' => 0,
            ];

            $this->repo->store($payload);

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