<?php

namespace App\Actions\Hrd;

use App\Services\GeneralService;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeePoint;
use Modules\Hrd\Models\EmployeePointProject;
use Modules\Hrd\Models\EmployeePointProjectDetail;
use Modules\Production\Models\Project;

class PointRecord
{
    use AsAction;

    /**
     * Rule for entertainment:
     * 1. Only update the employee_point_project_details when employee already have a point in selected project
     * 2. Entertainment only have a single point event they worked on a lot of tasks in the single project
     *
     * Complete step is:
     * 1. Check data by employee_id in the employee_points table
     *
     * @return void
     */
    public function handle(
        array $payload, string $projectUid, string $type
    ) {
        try {
            $generalService = new GeneralService;

            $projectId = $generalService->getIdFromUid($projectUid, new Project);
            foreach ($payload['points'] as $data) {
                $employeeId = $generalService->getIdFromUid($data['uid'], new Employee);

                $currentPoint = EmployeePoint::selectRaw('id')
                    ->where('employee_id', $employeeId)
                    ->first();

                if (! $currentPoint) {
                    $currentPoint = EmployeePoint::create([
                        'employee_id' => $employeeId,
                        'total_point' => 0, // will update in the last process
                        'type' => $type,
                    ]);
                }

                $pointProject = EmployeePointProject::select('id')
                    ->where('employee_point_id', $currentPoint->id)
                    ->where('project_id', $projectId)
                    ->first();
                if (! $pointProject) {
                    $pointProject = EmployeePointProject::create([
                        'employee_point_id' => $currentPoint->id,
                        'project_id' => $projectId,
                        'total_point' => $data['point'],
                        'additional_point' => $data['additional_point'],
                        'prorate_point' => $data['prorate_point'],
                        'original_point' => $data['original_point'],
                        'calculated_prorate_point' => $data['calculated_prorate_point'],
                    ]);
                }

                // add detail
                foreach ($data['tasks'] as $task) {
                    EmployeePointProjectDetail::create([
                        'point_id' => $pointProject->id,
                        'task_id' => $task,
                    ]);
                }

                // update total point
                $parent = EmployeePoint::select('id')
                    ->where('employee_id', $employeeId)
                    ->get();
                $child = EmployeePointProject::selectRaw('total_point')
                    ->whereIn('employee_point_id', collect($parent)->pluck('id')->toArray())
                    ->get();
                $totalPoint = collect($child)->pluck('total_point')->sum();
                EmployeePoint::where('employee_id', $employeeId)
                    ->update([
                        'total_point' => $totalPoint,
                    ]);
            }

            return true;
        } catch (\Throwable $th) {
            Log::error('error point record >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>', [$th]);

            return false;
        }
    }
}
