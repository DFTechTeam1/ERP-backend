<?php

namespace Modules\Inventory\Services;

use App\Enums\ErrorCode\Code;
use App\Enums\Inventory\RequestInventoryStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Inventory\Repository\RequestInventoryRepository;

class RequestInventoryService {
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

            $relation = ['requester:id,nickname'];

            if (!empty($search)) { // array
                $where = formatSearchConditions($search['filters'], $where);
            }

            $sort = "name asc";
            if (request('sort')) {
                $sort = "";
                foreach (request('sort') as $sortList) {
                    if ($sortList['field'] == 'name') {
                        $sort = $sortList['field'] . " {$sortList['order']},";
                    } else {
                        $sort .= "," . $sortList['field'] . " {$sortList['order']},";
                    }
                }

                $sort = rtrim($sort, ",");
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
                    $display = $url['scheme'] . '://' . $url['host'];

                    return [
                        'display' => $display,
                        'link' => $linkItem
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
                    'can_be_approve' => $list->status === RequestInventoryStatus::Requested->value && auth()->user()->can('approve_request_inventory') ? true : false,
                    'can_be_reject' => $list->status === RequestInventoryStatus::Requested->value && auth()->user()->can('reject_request_inventory') ? true : false,
                    'can_be_deleted' => $list->status === RequestInventoryStatus::Approved->value ? true : false,
                    'can_be_edited' => $list->status === RequestInventoryStatus::Approved->value || $list->status === RequestInventoryStatus::Rejected->value ? true : false,
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
     *
     * @param string $uid
     * @return array
     */
    public function show(string $uid): array
    {
        try {
            $data = $this->repo->show($uid, 'name,uid,id,description,price,quantity,purchase_source,purchase_link,status,approval_target,store_name');
            if (!empty(request('format_price'))) {
                $data->price = $data->withoutFormattingPrice()->price;
            }
            $approval = $this->employeeRepo->show('id', 'uid', [], 'id = ' . $data->approval_target[0]);
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

    /**
     * Store data
     *
     * @param array $data
     *
     * @return array
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
                    return getIdFromUid($target, new Employee());
                })->toArray();
            }

            $payloadJob = [
                'target' => $data['approval_target'],
                'requester' => $user->employee_id
            ];
            foreach ($data['items'] as $key => $item) {
                $item['requested_by'] = $user->employee_id;

                $payloadJob['items'][] = $item;

                $item['approval_target'] = $data['approval_target'];

                $this->repo->store($item);
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
            $data['approval_target'] = collect($data['approval_target'])->map(function ($target) {
                return getIdFromUid($target, new Employee());
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
     * @param string $type
     * @param string $uid
     * @return array
     */
    public function process(string $type, string $uid): array
    {
        if ($type == 'reject') {
            $status = RequestInventoryStatus::Rejected->value;
        } else if ($type == 'approved') {
            $status = RequestInventoryStatus::Approved->value;
        }

        $this->repo->update([
            'status' => $status
        ], $uid);

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
                'value' => $case->value
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
        DB::beginTransaction();;
        try {
            foreach ($ids['ids'] as $uid) {
                // transfer to inventory
                $payloadInventory = [
                    'item_type' => ''
                ];
            }

            DB::commit();

            return generalResponse(
                'success',
                false
            );
        } catch(\Throwable $th) {
            DB::rollBack();

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
                __('notification.successDeleteRequest'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
