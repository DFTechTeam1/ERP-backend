<?php

namespace App\Services;

use App\Enums\Cache\CacheKey;
use App\Enums\Production\ProjectStatus;
use App\Enums\System\BaseRole;
use DateTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DashboardService {
    private $projectRepo;

    private $inventoryRepo;

    private $employeeRepo;

    private $taskPic;

    private $taskPicHistory;

    private $taskPicLog;

    private $positionRepo;

    private $isEmployee;

    private $isDirector;

    private $isProjectManager;

    private $startDate;

    private $endDate;

    private $generalService;

    public function __construct(
        \Modules\Production\Repository\ProjectRepository $projectRepo,
        \Modules\Inventory\Repository\InventoryRepository $inventoryRepo,
        \Modules\Hrd\Repository\EmployeeRepository $employeeRepo,
        \Modules\Company\Repository\PositionRepository $positionRepo,
        \Modules\Production\Repository\ProjectTaskPicRepository $projectTaskPicRepo,
        \Modules\Production\Repository\ProjectTaskPicHistoryRepository $projectTaskPicHistoryRepo,
        \Modules\Production\Repository\ProjectTaskPicLogRepository $projectTaskPicLogRepo,
        GeneralService $generalService
    )
    {
        $this->generalService = $generalService;

        $this->projectRepo = $projectRepo;

        $this->inventoryRepo = $inventoryRepo;

        $this->employeeRepo = $employeeRepo;

        $this->positionRepo = $positionRepo;

        $this->taskPic = $projectTaskPicRepo;

        $this->taskPicHistory = $projectTaskPicHistoryRepo;

        $this->taskPicLog = $projectTaskPicLogRepo;
    }

    public function getReport()
    {
        $now = Carbon::parse('now');
        $this->startDate = $now->startOfMonth()->format('Y-m-d');
        $this->endDate = $now->endOfMonth()->format('Y-m-d');

        $output = [
            'left' => [
                [
                    'total' => 0,
                    'text' => '',
                ],
                [
                    'total' => 0,
                    'text' => '',
                ],
            ],
            'right' => [
                [
                    'total' => 0,
                    'series' => [],
                    'text' => '',
                ],
                [
                    'total' => 0,
                    'series' => [],
                    'text' => '',
                ],
            ]
        ];

        $user = auth()->user();

        $this->isEmployee = $user->is_employee;
        $this->isProjectManager = $user->is_project_manager;
        $this->isDirector = $user->is_director;

        $output = [];
        if ($this->isDirector || auth()->user()->email == 'admin@admin.com') {
            $output = $this->getReportDirector();
        } else if ($this->isProjectManager) {
            $output = $this->getReportProjectManager();
        } else if ($this->isEmployee) {
            $output = $this->getReportProduction();
        }

        return generalResponse(
            'success',
            false,
            $output
        );
    }

    protected function getProjectReport()
    {
        $whereHas = [];
        if ($this->isProjectManager) {
            $whereHas = [
                [
                    'relation' => 'personInCharges',
                    'query' => "pic_id = " . auth()->user()->employee_id,
                ],
            ];
        }

        $startDate = date('Y-m') . '-01';
        $year = date('Y', strtotime($startDate));
        $month = date('m', strtotime($startDate));
        $getLastDay = Carbon::createFromDate((int) $year, (int) $month, 1)
            ->endOfMonth()
            ->format('d');
        $endDate = date('Y-m') . '-' . $getLastDay;
        $where = "project_date >= '{$startDate}' and project_date <= '{$endDate}'";

        logging('where projects', [$where]);

        $projects = $this->projectRepo->list('id,status', $where, [], $whereHas);
        $projectsGroup = collect($projects)->groupBy('status_text')->toArray();
        $projectLabels = array_keys($projectsGroup);
        $projectSeries = [];
        foreach ($projectsGroup as $projectGroup) {
            $projectSeries[] = count($projectGroup);
        }
        $projectOptions = [
            'dataLabels' => [
                'enabled' => false,
            ],
            'legend' => [
                'show' => false,
            ],
            'responsive' => [
                [
                    'breakpoint' => 600,
                    'options' => [],
                ],
            ],
            'plotOptions' => [
                'pie' => [
                    'expandOnClick' => true,
                    'donut' => [
                        'labels' => [
                            'show' => true,
                        ],
                    ],
                ],
            ],
            'labels' => $projectLabels,
        ];

        return [
            'options' => $projectOptions,
            'total' => $projects->count(),
            'series' => $projectSeries,
        ];
    }

    // all in a month
    protected function getReportProduction()
    {
        $tasks = $this->taskPicHistory->list('id,project_task_id,project_id,employee_id', 'employee_id = ' . auth()->user()->employee_id, [
            'project' => function ($q) {
                $q->whereBetween('project_date', [$this->startDate, $this->endDate]);
            }
        ]);

        $tasks = collect((object) $tasks)->filter(function ($filter) {
            return $filter->project;
        });

        $group = collect($tasks)->groupBy("project_id")->toArray();

        $keys = array_keys($group);

        $totalTask = [];
        foreach ($group as $detail) {
            $totalTask[] = count($detail);
        }

        return [
            'left' => [
                [
                    'text' => __("global.totalTaskInMonth"),
                    'value' => array_sum($totalTask),
                ],
                [
                    'text' => __('global.totalProjectInMonth'),
                    'value' => count($keys),
                ]
            ],
            // 'right' => [
            //     [
            //         'text' => __('global.totalProjectInMonth'),
            //         'series' => $projects['series'],
            //         'options' => $projects['options'],
            //         'value' => $projects['total'],
            //     ],
            //     [
            //         'text' => __('global.upcomingProject'),
            //         'series' => $upcomingSeries,
            //         'options' => $upcomingOptions,
            //         'value' => $upcomingProject->count(),
            //     ],
            // ],
        ];
    }

    protected function getReportProjectManager()
    {
        $projects = $this->getProjectReport();

        // get upcomoing event (2 weeks for now)
        $startDate = date('Y-m-d', strtotime('-14 days'));
        $endDate = date('Y-m-d');
        $upcomingProject = $this->projectRepo->list(
            'id,classification,name,project_date',
            "project_date >= '{$startDate}' and project_date <= '{$endDate}'",
            [],
            [
                [
                    'relation' => 'personInCharges',
                    'query' => "pic_id = " . auth()->user()->employee_id,
                ]
            ]
        );
        $upcomingGroup = collect($upcomingProject)->groupBy('project_date')->toArray();
        $upcomingSeries = [];
        foreach ($upcomingGroup as $group) {
            $upcomingSeries[] = count($group);
        }
        $upcomingLabels = collect(array_keys($upcomingGroup))->map(function ($item) {
            return date('d F Y', strtotime($item));
        })->toArray();
        $upcomingOptions = [
            'dataLabels' => [
                'enabled' => false,
            ],
            'legend' => [
                'show' => false,
            ],
            'responsive' => [
                [
                    'breakpoint' => 600,
                    'options' => [],
                ],
            ],
            'plotOptions' => [
                'pie' => [
                    'expandOnClick' => true,
                    'donut' => [
                        'labels' => [
                            'show' => true,
                        ],
                    ],
                ],
            ],
            'labels' => $upcomingLabels,
        ];

        // get total team member
        $member = $this->employeeRepo->list('id', 'boss_id = ' . auth()->user()->employee_id);

        // get task to be checked
        $tasks = $this->taskPic->list('id', 'employee_id = ' . auth()->user()->employee_id);

        return [
            'left' => [
                [
                    'text' => __("global.taskToDo"),
                    'value' => $tasks->count(),
                ],
                [
                    'text' => __("global.totalTeamMember"),
                    'value' => $member->count(),
                ],
            ],
            'right' => [
                [
                    'text' => __('global.totalProjectInMonth'),
                    'series' => $projects['series'],
                    'options' => $projects['options'],
                    'value' => $projects['total'],
                ],
                [
                    'text' => __('global.upcomingProject'),
                    'series' => $upcomingSeries,
                    'options' => $upcomingOptions,
                    'value' => $upcomingProject->count(),
                ],
            ]
        ];
    }

    protected function getReportDirector()
    {
        $totalIncome = 0;

        // get equipment price
        $inventories = $this->inventoryRepo->list('purchase_price,stock,id', '', ['items:id,inventory_id,purchase_price']);
        $totalInventoryPrice = collect($inventories)->map(function ($item) {
            $itemsPrice = collect($item->items)->pluck('purchase_price')->sum();

            return $itemsPrice;
        })->sum();

        $employees = $this->employeeRepo->list('id,position_id', 'status != ' . \App\Enums\Employee\Status::Inactive->value, ['position:id,name']);
        $employeesGroup = collect($employees)->groupBy('position.name')->toArray();
        $positionLabels = array_keys($employeesGroup);
        $positionSeries = [];
        foreach ($employeesGroup as $employeeGroup) {
            $positionSeries[] = count($employeeGroup);
        }
        $positionOptions = [
            'dataLabels' => [
                'enabled' => false,
            ],
            'legend' => [
                'show' => false,
            ],
            'responsive' => [
                [
                    'breakpoint' => 600,
                    'options' => [],
                ],
            ],
            'plotOptions' => [
                'pie' => [
                    'expandOnClick' => true,
                    'donut' => [
                        'labels' => [
                            'show' => true,
                        ],
                    ],
                ],
            ],
            'labels' => $positionLabels,
        ];

        $projects = $this->projectRepo->list('id,status', "project_date >= '" . $this->startDate . "' and project_date <= '" . $this->endDate . "'");
        $projectsGroup = collect($projects)->groupBy('status_text')->toArray();
        $projectLabels = array_keys($projectsGroup);
        $projectSeries = [];
        foreach ($projectsGroup as $projectGroup) {
            $projectSeries[] = count($projectGroup);
        }
        $projectOptions = [
            'dataLabels' => [
                'enabled' => false,
            ],
            'legend' => [
                'show' => false,
            ],
            'responsive' => [
                [
                    'breakpoint' => 600,
                    'options' => [],
                ],
            ],
            'plotOptions' => [
                'pie' => [
                    'expandOnClick' => true,
                    'donut' => [
                        'labels' => [
                            'show' => true,
                        ],
                    ],
                ],
            ],
            'labels' => $projectLabels,
        ];

        return [
            'left' => [
                [
                    'text' => __("global.totalEquipmentPrice"),
                    'value' => 'Rp. ' . number_format($totalInventoryPrice, 2),
                    'is_hide_nominal' => true,
                ],
                [
                    'text' => __("global.totalIncome"),
                    'value' => 'Rp. ' . number_format($totalIncome, 2),
                    'is_hide_nominal' => true,
                ],
            ],
            'right' => [
                [
                    'text' => __('global.totalProjectInMonth'),
                    'series' => $projectSeries,
                    'options' => $projectOptions,
                    'value' => $projects->count(),
                ],
                [
                    'text' => __('global.totalEmployee'),
                    'series' => $positionSeries,
                    'options' => $positionOptions,
                    'value' => $employees->count(),
                ],
            ]
        ];
    }

    /**
     * Function to get project calendar based on user role and months
     *
     * @return array
     */
    public function getProjectCalendars(): array
    {
        $where = '';

        $month = request('month') == 0 ? date('m') : request('month');
        $year = request('year') == 0 ? date('Y') : request('year');
        $startDate = $year . '-' . $month . '-01';
        $getLastDay = Carbon::createFromDate((int) $year, (int) $month, 1)
            ->endOfMonth()
            ->format('d');
        $endDate = $year . '-' . $month . '-' . $getLastDay;

        $superUserRole = getSettingByKey('super_user_role');
        $projectManagerRole = json_decode(getSettingByKey('project_manager_role'));
        $user = auth()->user();
        $roles = $user->roles;
        $roleId = $roles[0]->id;
        $employeeId = $user->employee_id;

        $where = "project_date >= '" . $startDate . "' and project_date <= '" . $endDate . "'";

        $whereHas = [];

        if ($roleId != $superUserRole && in_array($roleId, $projectManagerRole) && $roles[0]->name != BaseRole::ProjectManagerAdmin->value) {
            $whereHas[] = [
                'relation' => 'personInCharges',
                'query' => 'pic_id = ' . $employeeId,
            ];
        } else if (isDirector() || isItSupport()) {
            $whereHas = [];
        } else if ($roleId != $superUserRole && !in_array($roleId, $projectManagerRole)) {
            $projectTaskPic = $this->taskPic->list('id,project_task_id', 'employee_id = ' . $employeeId);

            if ($projectTaskPic->count() > 0) {
                $projectTasks = collect($projectTaskPic)->pluck('project_task_id')->toArray();
                $projectTaskIds = implode("','", $projectTasks);
                $projectTaskIds = "'" . $projectTaskIds;
                $projectTaskIds .= "'";

                $hasQuery = "id IN (" . $projectTaskIds . ")";
            } else {
                $hasQuery = "id IN (0)";
            }

            $whereHas[] = [
                'relation' => 'tasks',
                'query' => $hasQuery,
            ];
        }

        $data = $this->projectRepo->list('id,uid,name,project_date,venue', $where, [
            'personInCharges:id,project_id,pic_id',
            'personInCharges.employee:id,uid,name',
            'vjs.employee:id,nickname'
        ], $whereHas, 'project_date ASC');

        logging('dashboard project calendar', ['where' => $where, 'wherehas' => $whereHas]);

        $out = [];
        foreach ($data as $projectKey => $project) {
            $pics = collect($project->personInCharges)->pluck('employee.name')->toArray();
            $pic = implode(', ', $pics);
            $project['pic'] = $pic;
            $project['project_date_text'] = date('d F Y', strtotime($project->project_date));
            $project['vj'] = $project->vjs->count() > 0 ? implode(',', collect($project->vjs)->pluck('employee.nickname')->toArray()) : '-';

            $out[] = [
                'key' => $project->uid,
                'highlight' => 'indigo',
                'project_date' => $project->project_date,
                'dot' => false,
                'popover' => [
                    'label' => $project->name,
                ],
                'dates' => date('d F Y', strtotime($project->project_date)),
                'order' => $projectKey,
                'customData' => $project,
            ];
        }

        // grouping by date (for custom data)
        $grouping = collect($out)->groupBy('project_date')->all();

        return generalResponse(
            'success',
            false,
            [
                'events' => $out,
                'group' => $grouping,
                'month' => $month,
                'year' => $year,
            ],
        );
    }

    public function needCompleteProject(): array
    {
        try {
            $user = auth()->user();
            $cacheId = CacheKey::ProjectNeedToBeComplete->value . auth()->id();

            $output = $this->generalService->getCache($cacheId);

            if (!$output) {
                $output = Cache::remember($cacheId, 60 * 60 * 2, function () use ($user) {
                    $status = [
                        ProjectStatus::OnGoing->value,
                        ProjectStatus::ReadyToGo->value,
                    ];
                    $whereHas = [];

                    if ($user->hasRole(BaseRole::ProjectManager->value)) {
                        $whereHas[] = [
                            'relation' => 'personInCharges',
                            'query' => "pic_id = " . $user->load('employee')->employee->id
                        ];
                    }

                    $where = "project_date < NOW() AND status IN (" . implode(',', $status) . ")";

                    $data = $this->projectRepo->list(
                        select: 'id,uid,name,project_date,status,classification',
                        where: $where,
                        relation: [
                            'personInCharges:id,project_id,pic_id',
                            'personInCharges.employee:id,nickname'
                        ],
                        has: [
                            'personInCharges'
                        ],
                        whereHas: $whereHas,
                        orderBy: "id DESC",
                    );

                    $data = collect((object) $data)->map(function ($project) {
                        $listPics = collect($project->personInCharges)->pluck('employee.nickname')->toArray();

                        $project['pics'] = $listPics;
                        $project['project_date_format'] = date('d F Y', strtotime($project->project_date));

                        unset($project['personInCharges']);

                        return $project;
                    })->toArray();

                    return $data;
                });
            }

            return generalResponse(
                message: "Success",
                data: $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get all project songs for entertainment division
     *
     * @return array
     */
    public function getProjectSong(): array
    {
        try {
            $user = auth()->user();

            $whereHas = [];

            if ($user->hasRole(BaseRole::Entertainment->value)) {
                $whereHas[] = [
                    'relation' => 'entertainmentTaskSong',
                    'query' => "employee_id = " . $user->load('employee')->employee->id
                ];
            }

            $projects = $this->projectRepo->list(
                select: 'id,name,project_date,uid',
                where: "project_date >= '" . date('Y-m-d') . "'",
                relation: [
                    'songs:id,project_id,name',
                    'songs.task:id,project_song_list_id,employee_id'
                ],
                whereHas: $whereHas,
                limit: 50
            );

            $projects = collect((object) $projects)->map(function ($mapping) {
                $mapping['project_date'] = date('d M Y', strtotime($mapping['project_date']));

                // grouping
                $assignSong = [];
                $unassignSong = [];

                foreach ($mapping->songs as $song) {
                    if ($song->task) {
                        $assignSong[] = $song;
                    }
                    if (!$song->task) {
                        $unassignSong[] = $song;
                    }
                }

                $mapping['assign_song'] = $assignSong;
                $mapping['unassign_song'] = $unassignSong;

                return $mapping;
            })->toArray();

            return generalResponse(
                message: 'success',
                data: $projects
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Function to get last 8 project deadline based on user role
     * Deadline will have some colors based on 'day left' like:
     * 1. Less than 1 week use red
     * 2. Less than 4 week use orange-darken-3
     * 3. Less than 3 month use green-accent-3
     *
     * @return array
     */
    public function getProjectDeadline(): array
    {
        $where = '';
        $whereHas = [];
        $superUserRole = getSettingByKey('super_user_role');
        $projectManagerRole = getSettingByKey('project_manager_role');
        $user = auth()->user();
        $roles = $user->roles;
        $roleId = $roles[0]->id;
        $employeeId = $user->employee_id;

        if ($roleId == $projectManagerRole) {
            // get project based project PIC
            $whereHas[] = [
                'relation' => 'personInCharges',
                'query' => "pic_id = {$employeeId}",
            ];
        } else if ($roleId != $projectManagerRole || $roleId != $superUserRole) {
            // get based on user task pic
            $projectTaskPic = $this->taskPic->list('id,project_task_id', 'employee_id = ' . $employeeId);
            if ($projectTaskPic->count() > 0) {
                $projectTasks = collect($projectTaskPic)->pluck('project_task_id')->toArray();
                $projectTaskIds = implode("','", $projectTasks);
                $projectTaskIds = "'" . $projectTaskIds;
                $projectTaskIds .= "'";

                $hasQuery = "id IN (" . $projectTaskIds . ")";
            } else {
                $hasQuery = "id IN (0)";
            }
            $whereHas[] = [
                'relation' => 'tasks',
                'query' => $hasQuery,
            ];
        }

        $where = "project_date >= '" . date('Y-m-d') . "' and status = " . \App\Enums\Production\ProjectStatus::OnGoing->value;

        $data = $this->projectRepo->list('id,uid,name,project_date', $where, [], $whereHas, 'project_date ASC', 8);

        $out = [];
        foreach ($data as $project) {
            // set deadline color
            $color = 'red';

            $now = time(); // or your date as well
            $your_date = strtotime($project->project_date);
            $datediff = $your_date - $now;

            $d = number_format(ceil($datediff / (60 * 60 * 24)));

            if ($d <= 7) {
                $color = 'red';
            } else if ($d > 7 && $d <= 31) {
                $color = 'orange-darken-3';
            } else if ($d > 31) {
                $color = 'green-accent-3';
            }

            $out[] = [
                'uid' => $project->uid,
                'color' => $color,
                'name' => $project->name,
                'project_date' => date('l, d F Y', strtotime($project->project_date)),
                'date_count' => __('global.dateCount', ['day' => $d]),
            ];
        }

        return generalResponse(
            'success',
            false,
            $out,
        );
    }
}
