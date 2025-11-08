<?php

namespace Modules\Production\Services;

use Modules\Production\Repository\ProjectRepository;

class InchargeService
{
    public function __construct(
        private readonly ProjectRepository $projectRepo,
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
                    'vjs.employee:id,nickname',
                    'personInCharges:id,project_id,pic_id',
                    'personInCharges.employee:id,nickname,avatar',
                    'vjAfpatAttendances:id,project_id,employee_id',
                    'vjAfpatAttendances.employee:id,nickname',
                    'marcommAttendances:id,project_id,employee_id',
                    'marcommAttendances.employee:id,nickname',
                    'marcommAfpatAttendances:id,project_id,employee_id',
                    'marcommAfpatAttendances.employee:id,nickname',
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
                    'entertainment_after_party' => null,
                    'entertainment_note' => null,
    
                    // marcomm division
                    'marcomm_vj' => null,
                    'marcomm_after_party' => null,
                    'marcomm_note' => null,
    
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
}