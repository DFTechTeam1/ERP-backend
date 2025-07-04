<?php

namespace Modules\Inventory\Services;

use App\Enums\Inventory\RequestInventoryStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Inventory\Jobs\NewRequestInventoryJob;
use Modules\Inventory\Jobs\ProcessRequestInventory;
use Modules\Inventory\Repository\RequestInventoryRepository;

class RequestInventoryService
{
    private $repo;

    private $employeeRepo;

    /**
     * Construction Data
     */
    public function __construct()
    {
        $this->repo = new RequestInventoryRepository;
        $this->employeeRepo = new EmployeeRepository;
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

            $relation = ['requester:id,nickname'];

            if (! empty($search)) { // array
                $where = formatSearchConditions($search['filters'], $where);
            }

            $sort = 'status asc';
            if (request('sort')) {
                $sort = '';
                foreach (request('sort') as $sortList) {
                    if ($sortList['field'] == 'name') {
                        $sort = $sortList['field']." {$sortList['order']},";
                    } else {
                        $sort .= ','.$sortList['field']." {$sortList['order']},";
                    }
                }

                $sort = rtrim($sort, ',');
                $sort = ltrim($sort, ',');
            }

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page,
                [],
                $sort
            );
            $totalData = $this->repo->list('id', $where)->count();

            $output = [];
            foreach ($paginated as $list) {
                $link = $list->purchase_link;
                $link = collect($link)->map(function ($linkItem) {
                    $url = parse_url($linkItem);
                    $display = $url['scheme'].'://'.$url['host'];

                    return [
                        'display' => $display,
                        'link' => $linkItem,
                    ];
                })->toArray();
                $output[] = [
                    'uid' => $list->uid,
                    'name' => $list->name,
                    'target' => $list->target_line,
                    'price' => $list->price,
                    'requested' => $list->requester->nickname,
                    'status' => $list->status_text,
                    'status_color' => $list->status_color,
                    'quantity' => $list->quantity,
                    'purchase_link' => $link,
                    'store_name' => $list->store_name,
                    'purchase_source' => $list->purchase_source,
                    'can_be_approve' => ($list->status === RequestInventoryStatus::Requested->value && $list->status != RequestInventoryStatus::Closed->value) && auth()->user()->can('approve_request_inventory') ? true : false,
                    'can_be_reject' => ($list->status === RequestInventoryStatus::Requested->value && $list->status != RequestInventoryStatus::Closed->value) && auth()->user()->can('reject_request_inventory') ? true : false,
                    'can_be_converted' => ($list->status === RequestInventoryStatus::Approved->value && $list->status != RequestInventoryStatus::Closed->value) && auth()->user()->can('approve_request_inventory') ? true : false,
                    'can_be_deleted' => $list->status === RequestInventoryStatus::Approved->value || $list->status === RequestInventoryStatus::Closed->value ? true : false,
                    'can_be_edited' => $list->status === RequestInventoryStatus::Approved->value || $list->status === RequestInventoryStatus::Rejected->value || $list->status === RequestInventoryStatus::Closed->value ? true : false,
                ];
            }

            return generalResponse(
                'Success',
                false,
                [
                    'paginated' => $output,
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
            $data = $this->repo->show($uid, 'name,uid,id,description,price,quantity,purchase_source,purchase_link,status,approval_target,store_name');
            if (! empty(request('format_price'))) {
                $data->price = $data->withoutFormattingPrice()->price;
            }
            $approval = $this->employeeRepo->show('id', 'uid', [], 'id = '.$data->approval_target[0]);
            $data['approval_target_uid'] = $approval->uid;

            return generalResponse(
                'success',
                false,
                $data->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function getApprovalLines()
    {
        $data = User::permission('approve_request_inventory')
            ->whereHas('employee')
            ->with('employee:id,name,uid')
            ->where('id', '!=', auth()->id())
            ->get();

        $output = [];
        if (count($data) > 0) {
            foreach ($data as $user) {
                $output[] = [
                    'value' => $user->employee->uid,
                    'title' => $user->employee->name,
                ];
            }
        }

        return generalResponse(
            'success',
            false,
            $output
        );
    }

    public function convertToInventory(array $data, string $uid)
    {
        DB::beginTransaction();
        try {
            $requestData = $this->repo->show($uid);
            $data['stock'] = $requestData['quantity'];
            $data['name'] = $requestData->name;

            $inventoryService = new InventoryService;
            $store = $inventoryService->store($data);

            if (! $store['error']) {
                // closed the request
                $this->repo->update([
                    'status' => RequestInventoryStatus::Closed->value,
                ], $uid);
            }

            DB::commit();

            return generalResponse(
                __('notification.requestConvertedToInventory'),
                false
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Store data
     */
    public function store(array $data): array
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();

            if (
                (isset($data['approval_target'])) &&
                (count($data['approval_target']) > 0)
            ) {
                $data['approval_target'] = collect($data['approval_target'])->map(function ($target) {
                    return getIdFromUid($target, new Employee);
                })->toArray();
            }

            $payloadJob = [
                'target' => $data['approval_target'],
                'requester' => $user->employee_id,
            ];
            foreach ($data['items'] as $key => $item) {
                $item['requested_by'] = $user->employee_id;

                $payloadJob['items'][] = $item;

                $item['approval_target'] = $data['approval_target'];

                $store = $this->repo->store($item);

                NewRequestInventoryJob::dispatch($store)->afterCommit();
            }

            DB::commit();

            return generalResponse(
                __('notification.requestInventoryCreated'),
                false
            );
        } catch (\Throwable $th) {
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
            $data['approval_target'] = collect($data['approval_target'])->map(function ($target) {
                return getIdFromUid($target, new Employee);
            })->toArray();

            $this->repo->update($data, $id);

            return generalResponse(
                __('notification.requestInventoryUpdated'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Process a request
     */
    public function process(string $type, string $uid): array
    {
        if ($type == 'reject') {
            $status = RequestInventoryStatus::Rejected->value;
            $rejectedBy = auth()->user()->employee_id;
        } elseif ($type == 'approved') {
            $status = RequestInventoryStatus::Approved->value;
            $approvedBy = auth()->user()->employee_id;
        }

        $this->repo->update([
            'status' => $status,
            'approved_by' => isset($approvedBy) ? $approvedBy : null,
            'rejected_by' => isset($rejectedBy) ? $rejectedBy : null,
        ], $uid);

        // sending notifiation
        ProcessRequestInventory::dispatch($uid);

        return generalResponse(
            $type == 'approved' ? __('notification.requestInventoryHasBeenApproved') : __('notification.requestInventoryHasBeenRejected'),
            false
        );
    }

    public function getRequestInventoryStatus()
    {
        $cases = RequestInventoryStatus::cases();
        $output = [];
        foreach ($cases as $case) {
            $output[] = [
                'title' => $case->label(),
                'value' => $case->value,
            ];
        }

        return generalResponse(
            'success',
            false,
            $output
        );
    }

    public function closedRequest(array $ids, array $data): array
    {
        DB::beginTransaction();
        try {
            foreach ($ids['ids'] as $uid) {
                // transfer to inventory
                $payloadInventory = [
                    'item_type' => '',
                ];
            }

            DB::commit();

            return generalResponse(
                'success',
                false
            );
        } catch (\Throwable $th) {
            DB::rollBack();

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
                __('notification.successDeleteRequest'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
