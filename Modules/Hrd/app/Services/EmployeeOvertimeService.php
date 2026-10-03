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

        if (isset($res['data']) && isset($res['data']['projects']) && count($res['data']['projects']) > 0) {
            foreach ($res['data']['projects'] as $project) {
                $projectData = $this->projectRepo->show(
                    uid: '',
                    select: 'id,name',
                    relation: ['tasks:id,name,project_id'],
                    where: "id = " . $project['id'],
                    whereHas: [
                        [
                            'relation' => 'tasks',
                            'query' => "id IN (" . implode(',', $project['tasks']) . ")"
                        ]
                    ]
                );

                if (isset($project['tasks']) && count($project['tasks']) > 0) {
                    // $tasks =
                }
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
                foreach ($projects as $project) {
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
