<?php

namespace App\Actions\Hrd;

use App\Services\GeneralService;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeePoint;
use Modules\Production\Models\Project;

class PointRecord
{
    use AsAction;

    public function handle(
        mixed $employeeIdentifier,
        mixed $projectIdentifier,
        int $taskId,
        string $source = 'entertainment',
        int $point = 0,
        int $additionalPoint = 0
    ) {
        $generalService = new GeneralService();

        $employeeId = gettype($employeeIdentifier) === 'string' ? $generalService->getIdFromUid($employeeIdentifier, new Employee()) : $employeeIdentifier;
        $projectId = gettype($projectIdentifier) === 'string' ? $generalService->getIdFromUid($projectIdentifier, new Project()) : $projectIdentifier;

        EmployeePoint::create([
            'employee_id' => $employeeId,
            'project_id' => $projectId,
            'point' => $point,
            'additional_point' => $additionalPoint,
            'task_type' => $source,
            'task_id' => $taskId
        ]);
    }
}
