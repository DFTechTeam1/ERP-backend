<?php

namespace Modules\Production\Services;

use App\Enums\System\BaseRole;
use App\Services\UserRoleManagement;
use Carbon\Carbon;
use Modules\Hrd\Services\EmployeeRepoGroup;
use Modules\Production\Repository\ProjectTaskPicRepository;

class TestingService
{
    private $projectGroupRepo;

    private $employeeRepoGroup;

    private $userRoleManagement;

    private $user;

    private $taskPicRepo;

    public function __construct(
        ProjectRepositoryGroup $projectGroupRepo,
        EmployeeRepoGroup $employeeRepoGroup,
        UserRoleManagement $userRoleManagement,
        ProjectTaskPicRepository $taskPicRepo
    ) {
        $this->taskPicRepo = $taskPicRepo;

        $this->projectGroupRepo = $projectGroupRepo;

        $this->employeeRepoGroup = $employeeRepoGroup;

        $this->userRoleManagement = $userRoleManagement;

        $this->user = auth()->user();
    }

    /**
     * Function to get selected employee task
     */
    protected function getProjectTaskRelationQuery(object $employee): array
    {
        if ($this->user->hasRole('entertainment')) { // just get event for entertainment. Look at transfer_team_members table
            $newWhereHas = [
                [
                    'relation' => 'teamTransfer',
                    'query' => 'employee_id = '.$this->user->employee_id,
                ],
            ];
        } else { // get based on task
            $taskIds = $this->projectGroupRepo->taskPicLogRepo->list('id,project_task_id', 'employee_id = '.$employee->id);
            $taskIds = collect($taskIds)->pluck('project_task_id')->unique()->values()->toArray();

            // get from project_task_pics table
            $taskPics = $this->taskPicRepo->list(
                select: 'project_task_id',
                where: "employee_id = {$employee->id}"
            );
            if ($taskPics->count() > 0) {
                $taskIds = collect($taskIds)
                    ->merge(
                        collect($taskPics)->pluck('project_task_id')->toArray()
                    )->unique()->values()->toArray();
            }

            if (count($taskIds) > 0) {
                $queryNewHas = 'id IN ('.implode(',', $taskIds).')';
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
     * Show project list for entertainment and project manager entertainment
     *
     * If user is have role 'entertainment', Show projects where the user is VJ and has a task on music
     */
    public function listForEntertainment(string $select = '*', string $where = '', array $relation = []): array
    {
        $relation[] = 'songs:id,uid,project_id,name,is_request_edit,is_request_delete';
        $relation[] = 'songs.task:id,project_song_list_id,employee_id';
        $relation[] = 'songs.task.employee:id,nickname';

        $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');

        $whereHas = [];

        $page = request('page') ?? 1;
        $page = $page == 1 ? 0 : $page;
        $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;

        $user = auth()->user();
        if ($user->hasRole(BaseRole::Entertainment->value)) {
            $whereHas[] = [
                'relation' => 'entertainmentTaskSong',
                'query' => 'employee_id = '.$user->load('employee')->employee->id,
            ];

            // condition to get project by vj
            $whereHas[] = [
                'relation' => 'vjs',
                'query' => 'employee_id = '.$user->load('employee')->employee->id,
                'type' => 'or',
            ];
        }

        $sorts = '';
        if (! empty(request('sortBy'))) {
            foreach (request('sortBy') as $sort) {
                if ($sort['key'] != 'pic' && $sort['key'] != 'uid') {
                    $sorts .= $sort['key'].' '.$sort['order'].',';
                }
            }

            $sorts = rtrim($sorts, ',');
        } else {
            $sorts = 'project_date ASC';
        }

        $paginated = $this->projectGroupRepo->projectRepo->pagination(
            $select,
            $where,
            $relation,
            $itemsPerPage,
            $page,
            $whereHas,
            $sorts
        );

        logging('PAGINATED', $paginated->toArray());

        $eventTypes = \App\Enums\Production\EventType::cases();
        $classes = \App\Enums\Production\Classification::cases();
        $statusses = \App\Enums\Production\ProjectStatus::cases();

        $paginated = collect((object) $paginated)->map(function ($item) use ($eventTypes, $classes, $statusses) {
            $pics = collect($item->personInCharges)->map(function ($pic) {
                return [
                    'name' => $pic->employee->name.'('.$pic->employee->employee_id.')',
                ];
            })->pluck('name')->values()->toArray();

            $picEid = collect($item->personInCharges)->pluck('employee.employee_id')->toArray();

            $marketing = $item->marketing ? $item->marketing->name : '-';

            $marketingData = collect($item->marketings)->pluck('marketing.name')->toArray();
            if ($item->marketings->count() > 0) {
                $marketing = $item->marketings[0]->marketing->name;
                if ($item->marketings->count() > 1) {
                    $marketing .= ', and +'.$item->marketings->count() - 1 .' more';
                }
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

            $projectIsComplete = $item->status == \App\Enums\production\ProjectStatus::Completed->value;
            $noPic = count($pics) == 0 ? true : false;
            $haveVj = $item->vjs->count() > 0 ? true : false;

            return [
                'uid' => $item->uid,
                'id' => $item->id,
                'marketing' => $marketing ?? '',
                'pic' => count($pics) > 0 ? implode(', ', $pics) : __('global.undetermined'),
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
                'songs' => $item->songs,
                'number_of_equipments' => $item->equipments->count(),
                'action' => [
                    'detail' => true,
                    'remove_all_vj' => $this->user->can('assign_vj') && ! $projectIsComplete && ! $noPic && $haveVj,
                    'assign_vj' => $this->user->can('assign_vj') && ! $projectIsComplete && ! $haveVj && ! $noPic,
                ],
            ];
        });

        $totalData = $this->projectGroupRepo->projectRepo->list('id', $where, [], $whereHas)->count();

        return generalResponse(
            message: 'success',
            error: false,
            data: [
                'paginated' => $paginated,
                'totalData' => $totalData,
                'itemPerPage' => (int) $itemsPerPage,
            ]
        );
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
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');

            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');
            $whereHas = [];

            $roles = $this->user->roles;
            $roleNames = collect($roles)->pluck('name')->toArray();

            $isProductionRole = $this->userRoleManagement->isProductionRole();
            $isEntertainmentRole = $this->userRoleManagement->isEntertainmentRole();

            $projectManagerRole = getSettingByKey('project_manager_role');
            $isPMRole = in_array($roles[0]->id, json_decode($projectManagerRole, true));

            // $filterResult = $this->buildFilterResult();

            if (request('filter_month') == 'true') {
                $startMonth = date('Y-m').'-01';
                $endDateOfMonth = Carbon::createFromDate(
                    (int) date('Y'),
                    (int) date('m'),
                    1
                )
                    ->endOfMonth()
                    ->format('d');
                $endMonth = date('Y-m').'-'.$endDateOfMonth;
                if (empty($where)) {
                    $where = "project_date BETWEEN '{$startMonth}' AND '{$endMonth}'";
                } else {
                    $where .= " and project_date BETWEEN '{$startMonth}' AND '{$endMonth}'";
                }
            }

            if (request('filter_year') == 'true') {
                $startMonth = date('Y').'-01-01';
                $endMonth = date('Y').'-12-31';

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
                if (! empty($search['name']) && empty($where)) {
                    $name = strtolower($search['name']);
                    $where = "lower(name) LIKE '%{$name}%'";
                } elseif (! empty($search['name']) && ! empty($where)) {
                    $name = $search['name'];
                    $where .= " AND lower(name) LIKE '%{$name}%'";
                }

                if (! empty($search['event_type']) && empty($where)) {
                    $eventType = strtolower($search['event_type']);
                    $where = "event_type = '{$eventType}'";
                } elseif (! empty($search['event_type']) && ! empty($where)) {
                    $eventType = $search['event_type'];
                    $where .= " AND event_type = '{$eventType}'";
                }

                if (! empty($search['classification']) && empty($where)) {
                    $classification = strtolower($search['classification']);
                    $where = "classification = '{$classification}'";
                } elseif (! empty($search['classification']) && ! empty($where)) {
                    $classification = $search['classification'];
                    $where .= " AND classification = '{$classification}'";
                }

                if (! empty($search['start_date']) && empty($where)) {
                    $start = date('Y-m-d', strtotime($search['start_date']));
                    $where = "project_date >= '{$start}'";
                } elseif (! empty($search['start_date']) && ! empty($where)) {
                    $start = date('Y-m-d', strtotime($search['start_date']));
                    $where .= " AND project_date >= '{$start}'";
                }

                if (! empty($search['end_date']) && empty($where)) {
                    $end = date('Y-m-d', strtotime($search['end_date']));
                    $where = "project_date <= '{$end}'";
                } elseif (! empty($search['end_date']) && ! empty($where)) {
                    $end = date('Y-m-d', strtotime($search['end_date']));
                    $where .= " AND project_date <= '{$end}'";
                }

                if (! empty($search['pic']) && empty($whereHas)) {
                    $pics = $search['pic'];
                    $pics = collect($pics)->map(function ($pic) {
                        $picId = getIdFromUid($pic, new \Modules\Hrd\Models\Employee);

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

            // override logic when user is entertianment
            if (
                in_array(BaseRole::Entertainment->value, $roleNames) ||
                in_array(BaseRole::ProjectManagerEntertainment->value, $roleNames)
            ) {
                return $this->listForEntertainment($select, $where, $relation);
            }

            $employeeId = $this->employeeRepoGroup->employeeRepo->show('dummy', 'id,boss_id', [], 'id = '.$this->user->employee_id);

            // get project that only related to authorized user
            if ($isProductionRole || $isEntertainmentRole || $this->user->hasRole(BaseRole::LeadModeller->value)) {

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
                        $inWhere = '(';
                        $inWhere .= $this->user->employee_id;
                        $inWhere .= ',';
                        $inWhere .= $bossId;
                        $inWhere .= ')';

                        $whereHas[] = [
                            'relation' => 'personInCharges',
                            'query' => "pic_id IN {$inWhere}",
                        ];
                    }

                    // get assistant task
                    //                    $assistantTaskCondition = $this->getProjectTaskRelationQuery($employee);

                } else {
                    if (! auth()->user()->hasRole(BaseRole::ProjectManagerAdmin->value)) {
                        $whereHas[] = [
                            'relation' => 'personInCharges',
                            'query' => 'pic_id = '.$this->user->employee_id,
                        ];
                    }
                }
            }

            $sorts = '';
            if (! empty(request('sortBy'))) {
                foreach (request('sortBy') as $sort) {
                    if ($sort['key'] != 'pic' && $sort['key'] != 'uid') {
                        $sorts .= $sort['key'].' '.$sort['order'].',';
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

            $paginated = collect((object) $paginated)->map(function ($item) use ($eventTypes, $classes, $statusses, $roles) {
                $pics = collect($item->personInCharges)->map(function ($pic) {
                    return [
                        'name' => $pic->employee->name.'('.$pic->employee->employee_id.')',
                    ];
                })->pluck('name')->values()->toArray();

                $picEid = collect($item->personInCharges)->pluck('employee.employee_id')->toArray();

                $marketing = $item->marketing ? $item->marketing->name : '-';

                $marketingData = collect($item->marketings)->pluck('marketing.name')->toArray();
                if ($item->marketings->count() > 0) {
                    $marketing = $item->marketings[0]->marketing ? $item->marketings[0]->marketing->name : '';
                    if ($item->marketings->count() > 1) {
                        $marketing .= ', and +'.$item->marketings->count() - 1 .' more';
                    }
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

                $projectIsComplete = $item->status == \App\Enums\production\ProjectStatus::Completed->value;
                $noPic = count($pics) == 0 ? true : false;
                $isFinalCheck = $item->status == \App\Enums\Production\ProjectStatus::ReadyToGo->value || $item->status == \App\Enums\Production\ProjectStatus::Completed->value ? true : false;
                $haveVj = $item->vjs->count() > 0 ? true : false;

                /**
                 * Determine user can take these action or not
                 * 1. Detail
                 * 2. Delete
                 * 3. Change status
                 * 4. Assign PIC
                 * 5. Subtitute PIC
                 * 6. Final Check
                 * 7. Remove all VJ
                 * 8. Assign VJ
                 * 9. Return Equipment
                 */

                return [
                    'uid' => $item->uid,
                    'id' => $item->id,
                    'marketing' => $marketing ?? '',
                    'pic' => count($pics) > 0 ? implode(', ', $pics) : __('global.undetermined'),
                    'no_pic' => $noPic,
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
                    'project_is_complete' => $projectIsComplete,
                    'vj' => $vj,
                    'have_vj' => $haveVj,
                    'is_final_check' => $isFinalCheck,
                    'need_return_equipment' => $needReturnEquipment,
                    'roles' => $roles,
                    'number_of_equipments' => $item->equipments->count(),
                    'action' => [
                        'delete' => $this->user->can('delete_project') ? true : false,
                        'detail' => true,
                        'change_status' => $this->user->can('change_project_status') && ! $projectIsComplete,
                        'assign_pic' => $this->user->can('assign_pic') && $noPic,
                        'subtitute_pic' => $this->user->can('assign_pic') && ! $noPic,
                        'final_check' => $this->user->can('final_check') && ! $noPic && ! $isFinalCheck,
                        'remove_all_vj' => $this->user->can('assign_vj') && ! $projectIsComplete && ! $noPic && $haveVj,
                        'assign_vj' => $this->user->can('assign_vj') && ! $projectIsComplete && ! $haveVj && ! $noPic,
                        'return_equipment' => $projectIsComplete && $needReturnEquipment,
                    ],
                ];
            });

            return generalResponse(
                'Success',
                false,
                [
                    'paginated' => $paginated,
                    'totalData' => $totalData,
                    'itemPerPage' => (int) $itemsPerPage,
                    'isAllItems' => $isAllItems,
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
