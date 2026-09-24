<?php

namespace Modules\Hrd\Services;

use App\Data\Hrd\Overtime\PayloadOvertimeDatabaseData;
use Modules\Hrd\Repository\EmployeeRepository;

class OvertimeParser
{
    public function __construct(
        private readonly EmployeeRepository $employeeRepo,
    ) {}

    public function parse(array $data): array
    {
        /** @var array<int, PayloadOvertimeDatabaseData> */
        $output = [];

        foreach ($output as $item) {

        }

        return $output;
    }

    public function formatSingleItem(array $item)
    {
        $employeeId = $item['empNo'];
        $employee = $this->employeeRepo->show(
            uid: '',
            select: 'id,name,position_id',
            where: "employee_id = {$employeeId}",
            relation: [
                'position:id,name'
            ]
        );

        if (! $employee) {}

        $output = new PayloadOvertimeDatabaseData(
            employee_id: $employee->id,
            overtime_hours: floatval($item['ovthours']),
            remark: $item['remark'],
            project_id: '',
            task_id: '',
            projecet_name: '',
            task_name: '',
            employee_name: '',
            position_name:
        );
    }
}
