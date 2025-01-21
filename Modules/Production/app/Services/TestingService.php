<?php

namespace Modules\Production\Services;

use App\Services\UserRoleManagement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Hrd\Services\EmployeeRepoGroup;

class TestingService {
    private $projectGroupRepo;

    private $employeeRepoGroup;

    private $userRoleManagement;

    private $user;

    public function __construct(
        ProjectRepositoryGroup $projectGroupRepo,
        EmployeeRepoGroup $employeeRepoGroup,
        UserRoleManagement $userRoleManagement
    )
    {
        $this->projectGroupRepo = $projectGroupRepo;

        $this->employeeRepoGroup = $employeeRepoGroup;

        $this->userRoleManagement = $userRoleManagement;

        $this->user = auth()->user();
    }

    /**
     * Function to get selected employee task
     *
     * @param object $employee;
     *
     * @return array
     */
    protected function getProjectTaskRelationQuery(object $employee): array
    {
        if ($this->user->hasRole('entertainment')) { // just get event for entertainment. Look at transfer_team_members table
            $newWhereHas = [
                [
                    'relation' => 'teamTransfer',
                    'query' => "employee_id = " . $this->user->employee_id,
                ],
            ];
        } else { // get based on task
            $taskIds = $this->projectGroupRepo->taskPicLogRepo->list('id,project_task_id', 'employee_id = ' . $employee->id);
            $taskIds = collect($taskIds)->pluck('project_task_id')->unique()->values()->toArray();

            if (count($taskIds) > 0) {
                $queryNewHas = 'id IN (' . implode(',', $taskIds) . ')';
            } else {
                $queryNewHas = 'id = 0';
            }

            $newWhereHas = [
                [
                    'relation' => 'tasks',
                    'query' => $queryNewHas,
                ],
            ];
        }

        return $newWhereHas;
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
    ): array {
        try {
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');

            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');
            $whereHas = [];

            $roles = $this->user->roles;

            $isProductionRole = $this->userRoleManagement->isProductionRole();
            $isEntertainmentRole = $this->userRoleManagement->isEntertainmentRole();

            $projectManagerRole = getSettingByKey('project_manager_role');
            $isPMRole = $roles[0]->id == $projectManagerRole;

            // $filterResult = $this->buildFilterResult();

            if (request('filter_month') == 'true') {
                $startMonth = date('Y-m') . '-01';
                $endDateOfMonth = Carbon::createFromDate(
                    (int) date('Y'),
                    (int) date('m'),
                    1
                )
                    ->endOfMonth()
                    ->format('d');
                $endMonth = date('Y-m') . '-' . $endDateOfMonth;
                if (empty($where)) {
                    $where = "project_date BETWEEN '{$startMonth}' AND '{$endMonth}'";
                } else {
                    $where .= " and project_date BETWEEN '{$startMonth}' AND '{$endMonth}'";
                }
            }

            if (request('filter_year') == 'true') {
                $startMonth = date('Y') . '-01-01';
                $endMonth = date('Y') . '-12-31';

                if (empty($where)) {
                    $where = "project_date BETWEEN '{$startMonth}' AND '{$endMonth}'";
                } else {
                    $where .= " and project_date BETWEEN '{$startMonth}' AND '{$endMonth}'";
                }
            }

            if (request('filter_today') == 'true') {
                $startDate = date('Y-m-d');
                if (empty($where)) {
                    $where = "project_date = '{$startDate}'";
                } else {
                    $where .= " and project_date = '{$startDate}'";
                }
            }

            if (
                ($search) &&
                (count($search) > 0)
            ) {
                if (!empty($search['name']) && empty($where)) {
                    $name = strtolower($search['name']);
                    $where = "lower(name) LIKE '%{$name}%'";
                } else if (!empty($search['name']) && !empty($where)) {
                    $name = $search['name'];
                    $where .= " AND lower(name) LIKE '%{$name}%'";
                }

                if (!empty($search['event_type']) && empty($where)) {
                    $eventType = strtolower($search['event_type']);
                    $where = "event_type = '{$eventType}'";
                } else if (!empty($search['event_type']) && !empty($where)) {
                    $eventType = $search['event_type'];
                    $where .= " AND event_type = '{$eventType}'";
                }

                if (!empty($search['classification']) && empty($where)) {
                    $classification = strtolower($search['classification']);
                    $where = "classification = '{$classification}'";
                } else if (!empty($search['classification']) && !empty($where)) {
                    $classification = $search['classification'];
                    $where .= " AND classification = '{$classification}'";
                }

                if (!empty($search['start_date']) && empty($where)) {
                    $start = date('Y-m-d', strtotime($search['start_date']));
                    $where = "project_date >= '{$start}'";
                } else if (!empty($search['start_date']) && !empty($where)) {
                    $start = date('Y-m-d', strtotime($search['start_date']));
                    $where .= " AND project_date >= '{$start}'";
                }

                if (!empty($search['end_date']) && empty($where)) {
                    $end = date('Y-m-d', strtotime($search['end_date']));
                    $where = "project_date <= '{$end}'";
                } else if (!empty($search['end_date']) && !empty($where)) {
                    $end = date('Y-m-d', strtotime($search['end_date']));
                    $where .= " AND project_date <= '{$end}'";
                }

                if (!empty($search['pic']) && empty($whereHas)) {
                    $pics = $search['pic'];
                    $pics = collect($pics)->map(function ($pic) {
                        $picId = getIdFromUid($pic, new \Modules\Hrd\Models\Employee());
                        return $picId;
                    })->toArray();
                    $picData = implode(',', $pics);
                    $whereHas = [
                        [
                            'relation' => 'personInCharges',
                            'query' => "pic_id IN ({$picData})",
                        ],
                    ];
                    // if ($isSuperAdmin) {
                    // }
                }
            }

            Log::debug('where project 1', [$where]);

            $employeeId = $this->employeeRepoGroup->employeeRepo->show('dummy', 'id,boss_id', [], 'id = ' . $this->user->employee_id);

            // get project that only related to authorized user
            if ($isProductionRole || $isEntertainmentRole) {

                if ($employeeId) {
                    // $taskIds = $this->taskPicLogRepo->list('id,project_task_id', 'employee_id = ' . $employeeId->id);
                    // $taskIds = collect($taskIds)->pluck('project_task_id')->unique()->values()->toArray();

                    // if (count($taskIds) > 0) {
                    //     $queryNewHas = 'id IN (' . implode(',', $taskIds) . ')';
                    // } else {
                    //     $queryNewHas = 'id = 0';
                    // }

                    // $newWhereHas = [
                    //     [
                    //         'relation' => 'tasks',
                    //         'query' => $queryNewHas,
                    //     ],
                    // ];

                    $newWhereHas = $this->getProjectTaskRelationQuery($employeeId);

                    $whereHas = array_merge($whereHas, $newWhereHas);
                }
            }

            $isAssistant = isAssistantPMRole();
            if ($isPMRole || $isAssistant) {
                if ($isAssistant) { // get assistant PM event and his boss event
                    // get boss project
                    $bossId = $employeeId->boss_id;

                    if ($bossId) {
                        $inWhere = "(";
                        $inWhere .= $this->user->employee_id;
                        $inWhere .= ",";
                        $inWhere .= $bossId;
                        $inWhere .= ")";

                        $whereHas[] = [
                            'relation' => 'personInCharges',
                            'query' => "pic_id IN {$inWhere}",
                        ];
                    }

                    // get assistant task
//                    $assistantTaskCondition = $this->getProjectTaskRelationQuery($employee);

                } else {
                    $whereHas[] = [
                        'relation' => 'personInCharges',
                        'query' => 'pic_id = ' . $this->user->employee_id,
                    ];
                }
            }

            $sorts = '';
            if (!empty(request('sortBy'))) {
                foreach (request('sortBy') as $sort) {
                    if ($sort['key'] != 'pic' && $sort['key'] != 'uid') {
                        $sorts .= $sort['key'] . ' ' . $sort['order'] . ',';
                    }
                }

                $sorts = rtrim($sorts, ',');
            }

            // condition when user want to see all projects
            $isAllItems = false;
            if ($itemsPerPage < 0) {
                $page = 1;

                $allProjects = $this->projectGroupRepo->projectRepo->list(
                    'id',
                    $where,
                    $relation,
                    $whereHas
                )->count();

                $itemsPerPage = $allProjects;
                $isAllItems = true;
            }

            Log::debug('where project', [$where]);

            $paginated = $this->projectGroupRepo->projectRepo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page,
                $whereHas,
                $sorts
            );
            $totalData = $this->projectGroupRepo->projectRepo->list('id', $where, [], $whereHas)->count();

            $eventTypes = \App\Enums\Production\EventType::cases();
            $classes = \App\Enums\Production\Classification::cases();
            $statusses = \App\Enums\Production\ProjectStatus::cases();

            $paginated = collect((object) $paginated)->map(function ($item) use ($eventTypes, $classes, $statusses) {
                $pics = collect($item->personInCharges)->map(function ($pic) {
                    return [
                        'name' => $pic->employee->name . '(' . $pic->employee->employee_id . ')',
                    ];
                })->pluck('name')->values()->toArray();

                $picEid = collect($item->personInCharges)->pluck('employee.employee_id')->toArray();

                $marketing = $item->marketing ? $item->marketing->name : '-';

                $marketingData = collect($item->marketings)->pluck('marketing.name')->toArray();
                $marketing = $item->marketings[0]->marketing->name;
                if ($item->marketings->count() > 1) {
                    $marketing .= ", and +" . $item->marketings->count() - 1 . " more";
                }

                $eventType = '-';
                foreach ($eventTypes as $et) {
                    if ($et->value == $item->event_type) {
                        $eventType = $et->label();
                    }
                }

                $status = '-';
                $statusColor = '';
                if ($item->status) {
                    foreach ($statusses as $statusData) {
                        if ($statusData->value == $item->status) {
                            $status = $statusData->label();
                            $statusColor = $statusData->color();
                        }
                    }
                } else {
                    $status = __('global.undetermined');
                    $statusColor = 'grey-lighten-1';
                }

                $eventClass = '-';
                $eventClassColor = null;
                foreach ($classes as $class) {
                    if ($class->value == $item->classification) {
                        $eventClass = $class->label();
                        $eventClassColor = $class->color();
                    }
                }

                $vj = '-';

                if ($item->vjs->count() > 0) {
                    $vj = implode(',', collect($item->vjs)->pluck('employee.nickname')->toArray());
                }

                $needReturnEquipment = false;
                if ($item->status == \App\Enums\production\ProjectStatus::Completed->value && $item->equipments->count() > 0) {
                    $needReturnEquipment = true;
                }
                if ($item->equipments->count() > 0) {
                    if ($item->equipments[0]->is_returned) {
                        $needReturnEquipment = false;
                    }
                }

                return [
                    'uid' => $item->uid,
                    'id' => $item->id,
                    'marketing' => $marketing,
                    'pic' => count($pics) > 0  ? implode(', ', $pics) : __('global.undetermined'),
                    'no_pic' => count($pics) == 0 ? true : false,
                    'pic_eid' => $picEid,
                    'name' => $item->name,
                    'project_date' => date('d F Y', strtotime($item->project_date)),
                    'venue' => $item->venue,
                    'event_type' => $eventType,
                    'led_area' => $item->led_area,
                    'event_class' => $item->projectClass->name,
                    'event_class_color' => $item->projectClass->color,
                    'status' => $status,
                    'status_color' => $statusColor,
                    'status_raw' => $item->status,
                    'project_is_complete' => $item->status == \App\Enums\production\ProjectStatus::Completed->value,
                    'vj' => $vj,
                    'have_vj' => $item->vjs->count() > 0 ? true : false,
                    'is_final_check' => $item->status == \App\Enums\Production\ProjectStatus::ReadyToGo->value || $item->status == \App\Enums\Production\ProjectStatus::Completed->value ? true : false,
                    'need_return_equipment' => $needReturnEquipment,
                ];
            });

            return generalResponse(
                'Success',
                false,
                [
                    'sort' => $sorts,
                    'paginated' => $paginated,
                    'totalData' => $totalData,
                    'itemPerPage' => (int) $itemsPerPage,
                    'isAllItems' => $isAllItems
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}