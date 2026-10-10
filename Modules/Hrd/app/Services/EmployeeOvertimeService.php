<?php

namespace Modules\Hrd\Services;

use App\Repository\UserRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Modules\Hrd\Repository\GreatdayApiLogRepository;
use Modules\Hrd\Exceptions\FailedFetchOvertimeFromGreatday;
use Modules\Hrd\Repository\EmployeeOvertimeRepository;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Repository\ProjectTaskRepository;
use Ramsey\Uuid\Uuid;

class EmployeeOvertimeService
{
    public function __construct(
        private readonly EmployeeOvertimeRepository $repo,
        private readonly EmployeeRepository $employeeRepo,
        private readonly UserRepository $userRepo,
        private readonly ProjectRepository $projectRepo,
        private readonly ProjectTaskRepository $projectTaskRepo,
        private readonly GreatdayApiLogRepository $apiLogRepo
    ) {}

    public function getDataFromGreatday(string $employeeNo): array
    {
        $service = app(GreatdayService::class);
        $url = '/overtime';
        $payload = [
            'page' => 1,
            'limit' => 100,
            'empNo' => $employeeNo
        ];
        $res = $service->authedPost($url, $payload);

        $creator = $this->userRepo->detail(id: Auth::id(), select: 'id,employee_id,email', relation: ['employee:id,name']);

        $isFailed = $res->failed();

        $payloadLog = [
            'url' => $url,
            'creator' => $creator ? ($creator->employee ? $creator->employee->name : $creator->email) : '-',
            'response' => json_encode($res->json()),
            'payload' => json_encode($payload),
            'is_success' => $isFailed ? false : true
        ];

        $this->apiLogRepo->store($payloadLog);

        if ($isFailed) {
            throw new FailedFetchOvertimeFromGreatday();
        }

        // formatting response, breakdown the code if needed
        return $this->formatResponse($res->json()['data']);
    }

    protected function buildPayloadItem(array $item, array &$payload, Collection $employees, array $projectData = []): void
    {
        $hours = floatval($item['ovthours']);
        $numberOfProjects = count($item['projects']);
        $perProjectOvertimeHour = $numberOfProjects <= 1 ? $hours : round($hours / $numberOfProjects);

        array_push($payload, [
            'uid' => Uuid::uuid4()->toString(),
            'employee_id' => $item['empNo'],
            'master_overtime_hours' => $hours,
            'overtime_hours' => $perProjectOvertimeHour,
            'remark' => $item['remark'],
            'project_id' => count($projectData) > 0 ? $projectData['project_id'] : null,
            'project_name' => count($projectData) > 0 ? $projectData['project_name'] : null,
            'task_id' => count($projectData) > 0 ? $projectData['task_id'] : null,
            'task_name' => count($projectData) > 0 ? $projectData['task_name'] : null,
            'employee_name' => $employees->firstWhere('employee_id', $item['empNo'])->name,
            'position_name' => $employees->firstWhere('employee_id', $item['empNo'])->position->name,
            'employee_number' => $item['empNo'],
            'overtime_date' => date('Y-m-d', strtotime($item['ovtDate'])),
            'status' => $item['status']
        ]);
    }

    protected function formatResponse(array $response): array
    {
        $data = $this->breakdownProjectData($response);

        // Get Employee list data
        $employeeNumbers = collect($data)->map(fn($val) => $val['empNo'])->unique()->implode("','");
        $employees = $this->employeeRepo->list(select: 'id,name,employee_id,position_id', where: "employee_id IN ('{$employeeNumbers}')", relation: ['position:id,name']);

        $payloadData = [];
        foreach ($data as $item) {
            if (count($item['projects']) > 0) {
                foreach ($item['projects'] as $project) {
                    $this->buildPayloadItem($item, $payloadData, $employees, $project);
                }
            } else {
                $this->buildPayloadItem($item, $payloadData, $employees);
            }
        }

        return $payloadData;
    }

    protected function breakdownProjectData(array $response): array
    {
        $token = request()->bearerToken();
        $output = [];
        foreach ($response as $key => $item) {
            $output[] = $item;

            $remark = $item['remark'];

            $data = Http::withToken($token)
                ->post(config('app.python_endpoint') . '/listener/project-task-identifier', [
                    'text' => $remark
                ]);

            if ($data->failed()) {
                continue;
            }

            $projectFormat = [];
            $res = $data->json();
            if (isset($res['data']) && isset($res['data']['projects']) && count($res['data']['projects']) > 0) {
                // logging('project result task detection', [
                //     'remark' => $remark,
                //     'data' => $res['data']['projects']
                // ]);
                // Get projects data
                $projectIds = collect($res['data']['projects'])->pluck('id')->implode(',');
                $projects = $this->projectRepo->list(select: 'id,name', where: "id IN ({$projectIds})");

                // Get task data
                $taskIds = collect($res['data']['projects'])->map(function ($project) {
                    return $project['tasks'];
                })->flatten()->implode(',');

                $tasks = null;
                if ($taskIds) {
                    $tasks = $this->projectTaskRepo->list(select: 'id,name', where: "id IN ({$taskIds})");
                }

                $projectFormat = collect($res['data']['projects'])->map(function ($item) use ($tasks, $projects) {
                    return [
                        'project_id' => $item['id'],
                        'task_id' => isset($tasks) ? $item['tasks'] : null,
                        'task_name' => isset($tasks) ? $tasks->whereIn('id', $item['tasks'])->pluck('name')->values() : null,
                        'project_name' => $projects->firstWhere('id', $item['id'])->name,
                    ];
                })->toArray();
            }

            $output[$key]['projects'] = $projectFormat;
        }

        return $output;
    }
}
