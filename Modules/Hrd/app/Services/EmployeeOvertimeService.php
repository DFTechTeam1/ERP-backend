<?php

namespace Modules\Hrd\Services;

use Illuminate\Support\Facades\Http;
use Modules\Hrd\Exceptions\FailedFetchOvertimeFromGreatday;
use Modules\Hrd\Repository\EmployeeOvertimeRepository;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Repository\ProjectRepository;

class EmployeeOvertimeService
{
    public function __construct(
        private readonly EmployeeOvertimeRepository $repo,
        private readonly EmployeeRepository $employeeRepo,
        private readonly ProjectRepository $projectRepo
    ) {}

    protected function getProjectData(string $remark): array
    {
        $response = Http::withToken(request()->bearerToken())
            ->post(config('app.python_endpoint') . "/listener/project-task-identifier", ['text' => $remark]);

        $output = [];

        if ($response->failed()) {
            // write log
        }

        // If success
        $res = $response->json();
        if (isset($res['data']) && isset($res['data']['task_ids']) && count($res['data']['task_ids']) > 0) {
            $project = $this->projectRepo->list(
                select: 'id,name',
                whereHas: [
                    [
                        'relation' => 'tasks',
                        'query' => "id IN (" . implode(',', $res['data']['task_ids']) . ")"
                    ]
                ],
                relation: [
                    'tasks:id,name,project_id'
                ]
            );

            if (!$project->count()) {
                // write log
            }

            foreach ($project as $key => $data) {
                $output[] = [
                    'id' => $data->id,
                    'project_name' => $data->name,
                    'tasks' => []
                ];

                $tasks = [];
                foreach ($data->tasks->whereIn('id', $res['data']['task_ids']) as $task) {
                    $tasks[] = [
                        'id' => $task->id,
                        'task_name' => $task->name
                    ];
                }

                $output[$key]['tasks'] = $tasks;
            }
        }

        return $output;
    }

    protected function buildOvertimePayload(array $item, array &$output) {}

    protected function parsePayloadDatabase(array $data)
    {
        $output = [];
        foreach ($data as $item) {
            $employee = $this->employeeRepo->show(
                uid: '',
                select: 'id,name',
                where: "employee_id = '" . $item['empNo'] . "'"
            );

            // Get project ids
            $projects = $this->getProjectData($data['remark']);

            if (count($projects) > 0) {
                foreach ($projects as $projectId) {
                    $output[] = [];
                }
            }

            $output[] = [
                'employee_id' => $employee->id,
                'overtime_hours' => $item['ovthours'],
                'remark' => $item['remark'],
                'project_id' => '',
                'task_id' => '',
                'task_name' => '',
                'project_name' => '',
                'employee_name' => '',
                'employee_number' => '',
                'overtime_date' => ''
            ];
        }
    }

    public function fetchFromGreatday(?string $employeeId, ?string $date)
    {
        try {
            $service = app(GreatdayService::class);

            $queries = [
                'page' => 1,
                'limit' => 100,
            ];

            if ($employeeId) {
                $queries['empNo'] = $employeeId;
            }

            if ($date) {
                $queries['date'] = $date;
            }

            $overtimes = $service->authedPost('/overtime', $queries);

            if ($overtimes->failed()) {
                throw new FailedFetchOvertimeFromGreatday();
            }

            $data = $overtimes->json()['data'];

            foreach ($data as $overtime) {
            }
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
