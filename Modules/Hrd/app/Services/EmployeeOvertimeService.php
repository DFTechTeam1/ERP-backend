<?php

namespace Modules\Hrd\Services;

use Modules\Hrd\Exceptions\FailedFetchOvertimeFromGreatday;
use Modules\Hrd\Repository\EmployeeOvertimeRepository;

class EmployeeOvertimeService
{
    public function __construct(
        private readonly EmployeeOvertimeRepository $repo
    ) {}

    protected function parsePayloadDatabase(array $data)
    {
        $output = [];
        foreach ($data as $item) {
            $employee =
                $output[] = [
                    'employee_id' => '',
                    'overtime_hours' => '',
                    'remark' => '',
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

    public function fetchFromGreatday(string $employeeGreatdayId)
    {
        try {
            $service = app(GreatdayService::class);

            $overtimes = $service->authedPost('/overtime', [
                'page' => 1,
                'limit' => 100,
                'empId' => $employeeGreatdayId
            ]);

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
