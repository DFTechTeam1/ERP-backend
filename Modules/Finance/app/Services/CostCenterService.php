<?php

namespace Modules\Finance\Services;

use App\Data\Finance\CostCenter\ListCostCenterData;
use App\Data\Finance\CostCenter\StoreCostCenterData;
use App\Data\Finance\CostCenter\UpdateCostCenterData;
use App\Exceptions\DataNotFound;
use Modules\Finance\Exceptions\Accounting\OnlyOneLevelCostCenterParentAllowed;
use Modules\Finance\Models\CostCenter;
use Modules\Finance\Repository\CostCenterRepository;

class CostCenterService
{
    /**
     * @param  CostCenterRepository  $repo  Repository used to query and persist cost centers.
     */
    public function __construct(
        private readonly CostCenterRepository $repo
    ) {}

    /**
     * List cost centers, paginated and filtered by the current request query string
     * (limit, search on name, type and active flag).
     *
     * @return array{error: bool, message: string, data?: array{totalData: int, paginated: array<int, ListCostCenterData>}, code?: int}
     *                                                                                                                                  The standard API response envelope carrying the total count and the page of rows.
     */
    public function list(): array
    {
        try {
            $limit = request('limit');
            $sortBy = request('sortBy');
            $search = request('search');
            $type = request('type');
            $active = request('active');

            $where = 'uid is not null';
            $condition = [];
            $whereLike = [];

            if ($search) {
                $whereLike['name'] = "%{$search}%";
            }
            if ($type) {
                $condition['type'] = $type;
            }
            if ($active) {
                $condition['is_active'] = $active;
            }

            $costCenters = $this->repo->paginate([
                'select' => ['uid', 'id', 'code', 'name', 'parent_id', 'is_active', 'type'],
                'with' => ['parent:id,uid,name'],
                'where' => $condition,
                'whereLike' => $whereLike,
            ], $limit);
            $totalData = $this->repo->get([
                'select' => ['id'],
                'whereRaw' => $where,
            ])->count();

            /** @var array<int, ListCostCenterData> */
            $output = [];

            foreach ($costCenters->items() as $item) {
                $output[] = new ListCostCenterData(
                    uid: $item->uid,
                    code: $item->code,
                    name: $item->name,
                    type: $item->type,
                    parentUid: $item?->parent?->uid ?? null,
                    parentName: $item?->parent?->name ?? '-',
                    transactionCount: 0,
                    isActive: $item->is_active,
                );
            }

            return generalResponse(
                message: __('global.success'),
                data: [
                    'totalData' => $totalData,
                    'paginated' => $output,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Resolve a parent cost center by uid and guarantee it is a top level node (only one level of
     * nesting is allowed).
     *
     * @param  string  $parentUid  The uid of the candidate parent cost center.
     * @return CostCenter The parent cost center (id and parent_id selected).
     *
     * @throws OnlyOneLevelCostCenterParentAllowed When the candidate parent already has a parent.
     */
    public function getParent(string $parentUid): CostCenter
    {
        $parent = $this->repo->show([
            'where' => [
                'uid' => $parentUid,
            ],
            'select' => ['id', 'parent_id'],
        ]);

        if ($parent->parent_id) {
            throw new OnlyOneLevelCostCenterParentAllowed;
        }

        return $parent;
    }

    /**
     * Create a cost center, resolving the optional parent uid to a parent_id first.
     *
     * @param  StoreCostCenterData  $payload  The validated create payload.
     * @return array{error: bool, message: string, data?: array<string, mixed>, code?: int} The
     *                                                                                      standard API response envelope.
     */
    public function store(StoreCostCenterData $payload): array
    {
        try {
            $payloadData = $payload->toArray();
            if ($payload->parent_uid) {
                $parent = $this->getParent($payload->parent_uid);

                unset($payloadData['parent_uid']);
                $payloadData['parent_id'] = $parent->id;
            }

            $this->repo->store($payloadData);

            return generalResponse(
                message: __('global.costCenterCreated'),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Resolve a cost center by uid or fail.
     *
     * @param  string  $costCenterUid  The uid to load.
     * @return CostCenter The matching cost center.
     *
     * @throws DataNotFound When no cost center matches the uid.
     */
    protected function show(string $costCenterUid): CostCenter
    {
        $costCenter = $this->repo->show([
            'where' => ['uid' => $costCenterUid],
        ]);
        if (! $costCenter) {
            throw new DataNotFound(__('notification.costCenterNotFound'), 404);
        }

        return $costCenter;
    }

    /**
     * Update a cost center, re-parenting or detaching from a parent as the payload requires.
     *
     * @param  UpdateCostCenterData  $payload  The validated update payload.
     * @param  string  $costCenterUid  The uid of the cost center to update.
     * @return array{error: bool, message: string, data?: array<string, mixed>, code?: int} The
     *                                                                                      standard API response envelope.
     */
    public function update(UpdateCostCenterData $payload, string $costCenterUid): array
    {
        try {
            $costCenter = $this->show($costCenterUid);

            $payloadUpdate = $payload->toArray();

            if ($payload->parent_uid) {
                $parent = $this->getParent($payload->parent_uid);
                if ($costCenter->parent_id != $parent->id) {
                    unset($payloadUpdate['parent_uid']);
                    $payloadUpdate['parent_id'] = $parent->id;
                }
            }

            // Remove parent id if needed
            if (! $payload->parent_uid && $costCenter->parent_id) {
                $payloadUpdate['parent_id'] = null;
            }

            $payloadUpdate['is_active'] = $costCenter->is_active;

            $this->repo->update($costCenter, $payloadUpdate);

            return generalResponse(
                message: __('global.costCenterUpdated')
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Toggle a cost center's active flag (active becomes inactive and vice versa).
     *
     * @param  string  $costCenterUid  The uid of the cost center to toggle.
     * @return array{error: bool, message: string, data?: array<string, mixed>, code?: int} The
     *                                                                                      standard API response envelope.
     */
    public function toggleStatus(string $costCenterUid): array
    {
        try {
            $costCenter = $this->show($costCenterUid);

            $this->repo->update($costCenter, [
                'is_active' => ! $costCenter->is_active,
            ]);

            return generalResponse(
                message: __('global.costCenterUpdated')
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete a cost center by uid.
     *
     * @param  string  $costCenterUid  The uid of the cost center to delete.
     * @return array{error: bool, message: string, data?: array<string, mixed>, code?: int} The
     *                                                                                      standard API response envelope.
     */
    public function delete(string $costCenterUid): array
    {
        try {
            $costCenter = $this->show($costCenterUid);

            $this->repo->delete($costCenter);

            return generalResponse(
                message: __('global.costCenterDeleted')
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
