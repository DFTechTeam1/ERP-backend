<?php

namespace Modules\Production\Services;

use App\Services\GeneralService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Production\Repository\ProjectMarcommAttendanceRepository;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Repository\ProjectVjAfpatAttendanceRepository;
use Modules\Production\Repository\ProjectVjRepository;
use Modules\Production\Repository\ProjectMarcommAfpatAttendanceRepository;

class InchargeService
{
    public function __construct(
        private readonly ProjectRepository $projectRepo,
        private readonly ProjectMarcommAttendanceRepository $projectMarcommAttendanceRepo,
        private readonly GeneralService $generalService,
        private readonly ProjectVjRepository $projectVjRepo,
        private readonly ProjectVjAfpatAttendanceRepository $projectVjAfpatAttendanceRepo,
        private readonly ProjectMarcommAfpatAttendanceRepository $projectMarcommAfpatAttendanceRepo,
    )
    {
        //
    }

    public function list()
    {
        try {
            $where = "1 = 1";
            $whereHas = [];
    
            $itemsPerPage = request('itemsPerPage') ?? 10;
            $itemsPerPage = $itemsPerPage == -1 ? 999999 : $itemsPerPage;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
    
            $paginated = $this->projectRepo->pagination(
                select: 'id,uid,name,project_date,with_after_party,event_type,venue,country_id,state_id,city_id',
                where: $where,
                relation: [
                    'vjs:id,project_id,employee_id',
                    'vjs.employee:id,name,nickname,uid',
                    'personInCharges:id,project_id,pic_id',
                    'personInCharges.employee:id,nickname,avatar',
                    'vjAfpatAttendances:id,project_id,employee_id',
                    'vjAfpatAttendances.employee:id,uid,name,nickname',
                    'marcommAttendances:id,project_id,employee_id',
                    'marcommAttendances.employee:id,nickname,uid,name',
                    'marcommAfpatAttendances:id,project_id,employee_id',
                    'marcommAfpatAttendances.employee:id,nickname,uid,name',
                    'transportation:id,project_id',
                    'country:id,name',
                    'state:id,name',
                    'city:id,name'
                ],
                itemsPerPage: $itemsPerPage,
                page: $page,
                sortBy: "project_date desc"
            );
            $totalData = $this->projectRepo->list(select: 'id', where: $where, whereHas: $whereHas)->count();
    
            $paginated = $paginated->map(function ($item) {
                return [
                    'uid' => $item->uid,
                    'event_id' => $item->id,
                    'event_date' => date('d F Y', strtotime($item->project_date)),
                    'event_name' => $item->name,
                    'event_pic' => $item->personInCharges->isNotEmpty() ? $item->personInCharges->pluck('employee.nickname')->implode(',') : '-',
                    'with_after_party' => $item->with_after_party ? true : false,
                    'event_pic_avatar' => null,
                    'event_type' => $item->event_type_text,
                    'event_type_color' => $item->event_type_color,
                    'venue' => $item->venue,
                    'venue_address' => $item->country->name . ($item->state ? ', ' . $item->state->name : '') . ($item->city ? ', ' . $item->city->name : ''),
                    'departure_date' => null,
                    'arrival_date' => null,
                    'ticket_claimed' => $item->transportation ? true : false,
                    
                    // entertainment division
                    'entertainment_vj' => $item->vjs->isNotEmpty() ? $item->vjs->pluck('employee.nickname')->implode(',') : '-',
                    'entertainment_after_party' => $item->vjAfpatAttendances->isNotEmpty() ? $item->vjAfpatAttendances->pluck('employee.nickname')->implode(',') : '-',
                    'entertainment_note' => $item->vjs->isNotEmpty() ? $item->vjs->first()->note : '-',
                    'entertainment_vj_employees' => $item->vjs->map(function ($vj) {
                        return [
                            'uid' => $vj->employee->uid,
                            'nickname' => $vj->employee->name,
                        ];
                    }),
                    'entertainment_afpat_employees' => $item->vjAfpatAttendances->map(function ($afpat) {
                        return [
                            'uid' => $afpat->employee->uid,
                            'nickname' => $afpat->employee->name,
                        ];
                    }),
    
                    // marcomm division
                    'marcomm_vj' => $item->marcommAttendances->isNotEmpty() ? $item->marcommAttendances->pluck('employee.nickname')->implode(',') : '-',
                    'marcomm_after_party' => $item->marcommAfpatAttendances->isNotEmpty() ? $item->marcommAfpatAttendances->pluck('employee.nickname')->implode(',') : '-',
                    'marcomm_note' => $item->marcommAttendances->isNotEmpty() ? $item->marcommAttendances->first()->note : '-',
                    'marcomm_employees' => $item->marcommAttendances->map(function ($marcomm) {
                        return [
                            'uid' => $marcomm->employee->uid,
                            'nickname' => $marcomm->employee->name,
                        ];
                    }),
                    'marcomm_afpat_employees' => $item->marcommAfpatAttendances->map(function ($afpat) {
                        return [
                            'uid' => $afpat->employee->uid,
                            'nickname' => $afpat->employee->name,
                        ];
                    }),
    
                    // interactive division
                    'interactive_vj' => null,
                    'interactive_after_party' => null,
                    'interactive_note' => null,
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

    /**
     * Update after party status
     * @param  array  $payload
     * @param  string  $projectUid
     * @return array
     */
    public function updateAfterPartyStatus(array $payload, string $projectUid): array
    {
        try {
            $this->projectRepo->update(
                data: $payload,
                id: $projectUid
            );

            return generalResponse(
                message: __('notification.afterPartyStatusUpdated'),
                data: []
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Assign On Duty Entertainment to Project. 
     * This action will assume if project do not has any VJ assigned yet.
     *
     * @param  array  $payload
     * @param  string  $projectUid
     * @return array
     */
    public function assignOnDutyEntertainment(array $payload, string $projectUid): array
    {
        DB::beginTransaction();
        
        try {
            $projectId = $this->generalService->getIdFromUid($projectUid, new \Modules\Production\Models\Project);

            // remove_main_event_uids -> remove current list
            if (!empty($payload['remove_main_event_uids'])) {
                foreach ($payload['remove_main_event_uids'] as $removeUid) {
                    $this->projectVjRepo->delete(
                        id: 'id',
                        where: "project_id = {$projectId} AND employee_id = (SELECT id FROM employees WHERE uid = '{$removeUid}')"
                    );
                }
            }

            // remove_after_party_uids -> remove current list
            if (!empty($payload['remove_after_party_uids'])) {
                foreach ($payload['remove_after_party_uids'] as $removeUid) {
                    $this->projectVjRepo->delete(
                        id: 'id',
                        where: "project_id = {$projectId} AND employee_id = (SELECT id FROM employees WHERE uid = '{$removeUid}')"
                    );
                }
            }

            // assign_main_event_uids -> add new list if not exists
            if (!empty($payload['assign_main_event_uids'])) {
                foreach ($payload['assign_main_event_uids'] as $assignUid) {
                    $exists = $this->projectVjRepo->list(
                        select: 'id',
                        where: "project_id = {$projectId} AND employee_id = (SELECT id FROM employees WHERE uid = '{$assignUid}')"
                    )->first();

                    if (! $exists) {
                        $this->projectVjRepo->store([
                            'project_id' => $projectId,
                            'employee_id' => $this->generalService->getIdFromUid($assignUid, new \Modules\Hrd\Models\Employee),
                            'created_by' => Auth::id(),
                            'note' => $payload['main_event_note'] ?? null,
                        ]);
                    }
                }
            }

            // assign_after_party_uids -> add new list
            if (!empty($payload['assign_after_party_uids'])) {
                foreach ($payload['assign_after_party_uids'] as $assignUid) {
                    $exists = $this->projectVjAfpatAttendanceRepo->list(
                        select: 'id',
                        where: "project_id = {$projectId} AND employee_id = (SELECT id FROM employees WHERE uid = '{$assignUid}')"
                    )->first();

                    if (! $exists) {
                        $this->projectVjAfpatAttendanceRepo->store([
                            'project_id' => $projectId,
                            'employee_id' => $this->generalService->getIdFromUid($assignUid, new \Modules\Hrd\Models\Employee),
                            'note' => $payload['after_party_note'] ?? null,
                        ]);
                    }
                }
            }
            
            DB::commit();

            return generalResponse(
                message: __('notification.entertainmentAssignedToProject'),
                data: []
            );
        } catch (\Throwable $th) {
            DB::rollBack();
            
            return errorResponse($th);
        }
    }

    /**
     * Assign On Duty Marcomm to Project. 
     * This action will assume if project do not has any VJ assigned yet.
     *
     * @param  array  $payload
     * @param  string  $projectUid
     * @return array
     */
    public function assignOnDutyMarcomm(array $payload, string $projectUid): array
    {
        DB::beginTransaction();
        
        try {
            $projectId = $this->generalService->getIdFromUid($projectUid, new \Modules\Production\Models\Project);

            // remove_main_event_uids -> remove current list
            if (!empty($payload['remove_main_event_uids'])) {
                foreach ($payload['remove_main_event_uids'] as $removeUid) {
                    $this->projectMarcommAttendanceRepo->delete(
                        id: 'id',
                        where: "project_id = {$projectId} AND employee_id = (SELECT id FROM employees WHERE uid = '{$removeUid}')"
                    );
                }
            }

            // remove_after_party_uids -> remove current list
            if (!empty($payload['remove_after_party_uids'])) {
                foreach ($payload['remove_after_party_uids'] as $removeUid) {
                    $this->projectMarcommAfpatAttendanceRepo->delete(
                        id: 'id',
                        where: "project_id = {$projectId} AND employee_id = (SELECT id FROM employees WHERE uid = '{$removeUid}')"
                    );
                }
            }

            // assign_main_event_uids -> add new list if not exists
            if (!empty($payload['assign_main_event_uids'])) {
                foreach ($payload['assign_main_event_uids'] as $assignUid) {
                    $exists = $this->projectMarcommAttendanceRepo->list(
                        select: 'id',
                        where: "project_id = {$projectId} AND employee_id = (SELECT id FROM employees WHERE uid = '{$assignUid}')"
                    )->first();

                    if (! $exists) {
                        $this->projectMarcommAttendanceRepo->store([
                            'project_id' => $projectId,
                            'employee_id' => $this->generalService->getIdFromUid($assignUid, new \Modules\Hrd\Models\Employee),
                            'created_by' => Auth::id(),
                            'note' => $payload['main_event_note'] ?? null,
                        ]);
                    }
                }
            }

            // assign_after_party_uids -> add new list
            if (!empty($payload['assign_after_party_uids'])) {
                foreach ($payload['assign_after_party_uids'] as $assignUid) {
                    $exists = $this->projectMarcommAfpatAttendanceRepo->list(
                        select: 'id',
                        where: "project_id = {$projectId} AND employee_id = (SELECT id FROM employees WHERE uid = '{$assignUid}')"
                    )->first();

                    if (! $exists) {
                        $this->projectMarcommAfpatAttendanceRepo->store([
                            'project_id' => $projectId,
                            'employee_id' => $this->generalService->getIdFromUid($assignUid, new \Modules\Hrd\Models\Employee),
                            'note' => $payload['after_party_note'] ?? null,
                        ]);
                    }
                }
            }
            
            DB::commit();

            return generalResponse(
                message: __('notification.marcommAssignedToProject'),
                data: []
            );
        } catch (\Throwable $th) {
            DB::rollBack();
            
            return errorResponse($th);
        }
    }
}