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
     */
    public function handle(
        array $payload, string $projectUid, string $type, bool $isRegularEmployee = true
    ) {
        if ($isRegularEmployee) {
            return $this->handleRegularEmployeePoints($payload, $projectUid, $type);
        } else {
            return $this->handleSpecialEmployeePoints($payload, $projectUid, $type);
        }
    }

    public function handleRegularEmployeePoints(
        array $payload, string $projectUid, string $type
    ): bool {
        // Implementation for regular employees (if needed)
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

    /**
     * Handle point recording for special employees with support for multiple PIC assessments.
     * 
     * This method handles special employee points across multiple PIC submissions:
     * - First PIC: Creates initial point record
     * - Subsequent PICs: Updates existing record by accumulating additional points
     * 
     * Rule:
     * 1. Check if special employee already has a point record for this specific project
     * 2. If exists, UPDATE the record by adding new additional_point from current PIC
     * 3. If not exists, CREATE new point record with initial points
     * 4. Always recalculate total_point = point + accumulated additional_point
     * 
     * @param array $payload - Contains points array with employee data
     * @param string $projectUid - The project UID
     * @param string $type - Type of point (production/entertainment)
     * @return bool
     */
    public function handleSpecialEmployeePoints(
        array $payload, string $projectUid, string $type
    ): bool {
        try {
            $generalService = new GeneralService;
            $projectId = $generalService->getIdFromUid($projectUid, new Project);

            foreach ($payload['points'] as $data) {
                // Skip if not a special employee
                if (!isset($data['is_special_employee']) || $data['is_special_employee'] != 1) {
                    continue;
                }

                $employeeId = $generalService->getIdFromUid($data['uid'], new Employee);

                // Check if this special employee already has a point record for this project
                $existingRecord = $this->checkExistingSpecialEmployeePoint($employeeId, $projectId);

                if ($existingRecord) {
                    // Special employee already has points for this project
                    // Update by accumulating additional points from current PIC
                    $oldAdditionalPoint = $existingRecord->additional_point;
                    $newAdditionalPoint = $oldAdditionalPoint + $data['additional_point'];
                    
                    // Recalculate total point (base point + accumulated additional points)
                    $basePoint = $data['point'];
                    $newTotalPoint = $basePoint + $newAdditionalPoint;
                    
                    $existingRecord->update([
                        'additional_point' => $newAdditionalPoint,
                        'total_point' => $newTotalPoint,
                        'prorate_point' => $data['prorate_point'],
                        'calculated_prorate_point' => $data['calculated_prorate_point'],
                    ]);

                    // Add new task details if available
                    if (isset($data['tasks']) && is_array($data['tasks'])) {
                        foreach ($data['tasks'] as $task) {
                            // Check if task detail already exists to avoid duplicates
                            $existingTaskDetail = EmployeePointProjectDetail::where('point_id', $existingRecord->id)
                                ->where('task_id', $task)
                                ->first();
                            
                            if (!$existingTaskDetail) {
                                EmployeePointProjectDetail::create([
                                    'point_id' => $existingRecord->id,
                                    'task_id' => $task,
                                ]);
                            }
                        }
                    }

                    // Update total point for this employee across all projects
                    $this->updateEmployeeTotalPoint($employeeId);

                    Log::info('Special employee point updated successfully', [
                        'employee_id' => $employeeId,
                        'project_id' => $projectId,
                        'point_project_id' => $existingRecord->id,
                        'old_additional_point' => $oldAdditionalPoint,
                        'new_additional_point' => $newAdditionalPoint,
                        'new_total_point' => $newTotalPoint,
                    ]);
                    
                    continue;
                }

                // If no existing record, proceed with creating new point record
                $currentPoint = EmployeePoint::selectRaw('id')
                    ->where('employee_id', $employeeId)
                    ->first();

                if (!$currentPoint) {
                    $currentPoint = EmployeePoint::create([
                        'employee_id' => $employeeId,
                        'total_point' => 0,
                        'type' => $type,
                    ]);
                }

                // Create new point project record for special employee
                $pointProject = EmployeePointProject::create([
                    'employee_point_id' => $currentPoint->id,
                    'project_id' => $projectId,
                    'total_point' => $data['point'] + $data['additional_point'],
                    'additional_point' => $data['additional_point'],
                    'prorate_point' => $data['prorate_point'],
                    'original_point' => $data['original_point'],
                    'calculated_prorate_point' => $data['calculated_prorate_point'],
                ]);

                // Add task details if available
                if (isset($data['tasks']) && is_array($data['tasks'])) {
                    foreach ($data['tasks'] as $task) {
                        EmployeePointProjectDetail::create([
                            'point_id' => $pointProject->id,
                            'task_id' => $task,
                        ]);
                    }
                }

                // Update total point for this employee across all projects
                $this->updateEmployeeTotalPoint($employeeId);

                Log::info('Special employee point created successfully', [
                    'employee_id' => $employeeId,
                    'project_id' => $projectId,
                    'point_project_id' => $pointProject->id,
                    'base_point' => $data['point'],
                    'additional_point' => $data['additional_point'],
                    'total_point' => $data['point'] + $data['additional_point'],
                ]);
            }

            return true;
        } catch (\Throwable $th) {
            Log::error('Error recording special employee points', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
                'project_uid' => $projectUid,
            ]);

            return false;
        }
    }

    /**
     * Check if special employee already has a point record for the specific project.
     * 
     * @param int $employeeId
     * @param int $projectId
     * @return EmployeePointProject|null
     */
    private function checkExistingSpecialEmployeePoint(int $employeeId, int $projectId): ?EmployeePointProject
    {
        // Get all employee point records for this employee
        $employeePoints = EmployeePoint::where('employee_id', $employeeId)
            ->pluck('id')
            ->toArray();

        if (empty($employeePoints)) {
            return null;
        }

        // Check if any point project exists for this employee in this project
        return EmployeePointProject::whereIn('employee_point_id', $employeePoints)
            ->where('project_id', $projectId)
            ->first();
    }

    /**
     * Update the total point for an employee by summing all their project points.
     * 
     * @param int $employeeId
     * @return void
     */
    private function updateEmployeeTotalPoint(int $employeeId): void
    {
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
}
