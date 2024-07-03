<?php

namespace App\Services;

use DateTime;
use Carbon\Carbon;

class DashboardService {
    private $projectRepo;

    private $taskPicRepo;

    public function __construct()
    {
        $this->projectRepo = new \Modules\Production\Repository\ProjectRepository();

        $this->taskPicRepo = new \Modules\Production\Repository\ProjectTaskPicRepository();
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
        $projectManagerRole = getSettingByKey('project_manager_role');
        $user = auth()->user();
        $roles = $user->roles;
        $roleId = $roles[0]->id;
        $employeeId = $user->employee_id;

        $where = "project_date >= '" . $startDate . "' and project_date <= '" . $endDate . "'";

        $whereHas = [];

        if ($roleId != $superUserRole && $roleId == $projectManagerRole) {
            $whereHas[] = [
                'relation' => 'personInCharges',
                'query' => 'pic_id = ' . $employeeId,
            ];
        } else if ($roleId != $superUserRole && $roleId != $projectManagerRole) {
            $projectTaskPic = $this->taskPicRepo->list('id,project_task_id', 'employee_id = ' . $employeeId);
            $projectTasks = collect($projectTaskPic)->pluck('project_task_id')->toArray();
            $projectTaskIds = implode("','", $projectTasks);
            $projectTaskIds = "'" . $projectTaskIds;
            $projectTaskIds .= "'";

            $whereHas[] = [
                'relation' => 'tasks',
                'query' => "id IN (" . $projectTaskIds . ")"
            ];
        }

        $data = $this->projectRepo->list('id,uid,name,project_date,venue', $where, [
            'personInCharges:id,project_id,pic_id',
            'personInCharges.employee:id,uid,name',
        ], $whereHas, 'project_date ASC');

        $out = [];
        foreach ($data as $projectKey => $project) {
            $pics = collect($project->personInCharges)->pluck('employee.name')->toArray();
            $pic = implode(', ', $pics);
            $project['pic'] = $pic;
            $project['project_date_text'] = date('d F Y', strtotime($project->project_date));

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
        } else if ($roleId != $projectManagerRole && $roleId != $superUserRole) {
            // get based on user task pic
            $projectTaskPic = $this->taskPicRepo->list('id,project_task_id', 'employee_id = ' . $employeeId);
            $projectTasks = collect($projectTaskPic)->pluck('project_task_id')->toArray();
            $projectTaskIds = implode("','", $projectTasks);
            $projectTaskIds = "'" . $projectTaskIds;
            $projectTaskIds .= "'";

            $whereHas[] = [
                'relation' => 'tasks',
                'query' => "id IN (" . $projectTaskIds . ")"
            ];
        }

        $data = $this->projectRepo->list('id,uid,name,project_date', $where, [], $whereHas, 'project_date ASC', 8);

        $out = [];
        foreach ($data as $project) {
            // set deadline color
            $deadline = new DateTime($project->project_date);
            $now = new DateTime('now');
            $diff = date_diff($now, $deadline);
            $d = $diff->d;
            $color = 'red';

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