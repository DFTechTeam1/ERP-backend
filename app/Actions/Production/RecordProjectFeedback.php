<?php

namespace App\Actions\Production;

use App\Services\GeneralService;
use Illuminate\Contracts\Auth\Authenticatable;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Repository\ProjectFeedbackRepository;

class RecordProjectFeedback
{
    use AsAction;

    /**
     * Here we will record project feedback based on project person in charge (PIC).
     * If project have more than one PIC, we will record feedback for each PIC.
     * @param array $payload
     * @param string $projectUid
     * @return bool
     */
    public function handle(array $payload, string $projectUid, Authenticatable $user): bool
    {
        $employeeId = $user->employee_id;
        $projectId = (new GeneralService)->getIdFromUid($projectUid, new \Modules\Production\Models\Project);

        (new ProjectFeedbackRepository)->store(
            data: [
                'project_id' => $projectId,
                'pic_id' => $employeeId,
                'feedback' => $payload['feedback'],
                'points' => $payload['points'] ?? null,
                'submitted_at' => now(),
                'submitted_by' => $employeeId,
            ]
        );

        // check whether all PICs have given their assessments or not
        $project = (new \Modules\Production\Repository\ProjectRepository)->show(
            uid: $projectUid,
            select: 'id',
            relation: [
                'personInCharges:id,project_id,pic_id'
            ]
        );

        $currentFeedbackRecord = (new ProjectFeedbackRepository)->list(
            select: 'id',
            where: "project_id = {$projectId}"
        );

        $out = false;
        if ($project->personInCharges->count() == $currentFeedbackRecord->count()) {
            $out = true;
        }

        return $out;
    }
}
