<?php

namespace App\Actions\Project;

use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Hrd\Models\EmployeePoint;
use Modules\Hrd\Repository\EmployeeTaskPointRepository;
use Modules\Production\Models\Project;

class GetProjectStatistic
{
    use AsAction;

    /**
    * Get detail project report
    * Step to produce:
    * 1. Get all project pic
    * 2. Get point based on employee id
    *
    */
    public function getProjectReport(array $project)
    {
        $teams = $project['teams'];
        $projectId = getIdFromUid($project['uid'], new Project());

        $output = [];

        foreach ($teams as $team) {
            $employeePoint = EmployeePoint::selectRaw('id,employee_id')
                ->with([
                    'projects' => function ($query) use($projectId) {
                        $query->selectRaw('id,employee_point_id,project_id,total_point,additional_point')
                            ->with(['project:id,name,project_date', 'details:id,point_id'])
                            ->whereHas('project', function ($queryProject) use($projectId) {
                                $queryProject->where('id', $projectId);
                            });
                    },
                    'employee:id,name'
                ])
                ->where('employee_id', $team['id'])
                ->first();

            if ($employeePoint) {
                $output[] = [
                    'name' => $employeePoint->employee->name,
                    'employee_id' => $employeePoint->employee_id,
                    'total_point' => isset($employeePoint->projects[0]) ? $employeePoint->projects[0]->total_point : 0,
                    'additional_point' => isset($employeePoint->projects[0]) ? $employeePoint->projects[0]->additional_point: 0,
                    'total_task' => isset($employeePoint->projects[0]) ? $employeePoint->projects[0]->details->count() : 0
                ];
            }
        }

        $firstLine = count($output) > 2 ? array_splice($output, 0, 2) : $output;

        $moreLine = count($output) > 2 ? $output : [];

        $resp = [
            'first_line' => $firstLine,
            'more_line' => $moreLine,
        ];

        return $resp;
    }

    public function handle($project)
    {
        return $this->getProjectReport($project);
        $employeeTaskPoint = new EmployeeTaskPointRepository();

        $projectId = getIdFromUid($project['uid'], new \Modules\Production\Models\Project());
        $teams = $project['teams'];

        $output = [];
        $resp = [];

        $checkPoint = $employeeTaskPoint->list('*', 'project_id = ' . $projectId);

        if ($checkPoint->count() > 0) {
            foreach ($teams as $key => $team) {
                $output[$key] = $team;

                // get points
                $point = $employeeTaskPoint->show('dummy', '*', [], 'employee_id = ' . $team['id'] . ' and project_id = ' . $projectId);

                $output[$key]['points'] = [
                    'total_task' => $point ? $point->total_task : 0,
                    'point' => $point ? $point->point : 0,
                    'additional_point' => $point ? $point->additional_point : 0,
                    'total_point' => $point ? $point->additional_point + $point->point : 0,
                ];
            }

            $output = collect($output)->map(function ($item) {
                $name = $item['name'];
                $expName = explode(' ', $name);

                return [
                    'uid' => $item['uid'],
                    'name' => $item['name'],
                    'nickname' => $expName[0],
                    'total_task' => $item['points']['total_task'],
                    'total_point' => $item['points']['total_point'],
                    'point' => $item['points']['point'],
                    'additional_point' => $item['points']['additional_point'],
                ];
            })->toArray();

            if (count($output) > 0) {
                $firstLine = count($output) > 2 ? array_splice($output, 0, 2) : $output;

                $moreLine = count($output) > 2 ? $output : [];

                $resp = [
                    'first_line' => $firstLine,
                    'more_line' => $moreLine,
                ];
            }
        }

        return $resp;
    }
}
