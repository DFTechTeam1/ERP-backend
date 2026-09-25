<?php

namespace App\Actions\Production;

use App\Enums\Production\ProjectActivityItem;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Repository\ProjectRepository;

class ProjectActivityRecord
{
    use AsAction;

    public function handle(int $userId, ProjectActivityItem $action, string $projectUid, ?string $from = null, ?string $to = null)
    {
        $repo = app(ProjectRepository::class);
        $employeeRepo = app(EmployeeRepository::class);

        $employee = $employeeRepo->show(uid: '', select: 'id,name', where: "user_id = {$userId}");

        $project = $repo->show(uid: $projectUid, select: 'id,uid,name,project_date');
        $project->activities()->create([
            'action' => $action->description($from, $to),
            'actor' => $employee->name,
        ]);
    }
}
