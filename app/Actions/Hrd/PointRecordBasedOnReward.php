<?php

namespace App\Actions\Hrd;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeePointProjectRepository;
use Modules\Hrd\Repository\EmployeePointRepository;
use Modules\Production\Models\Project;
use Modules\Production\Repository\ProjectRepository;

/**
 * Record production points and the reward payout for a completed project.
 *
 * Points are unchanged: one point per task-PIC-history row, plus any manually granted
 * additional_point. The reward follows the "Pot Produksi Fixed per Kelas" schema
 * (docs/Simulasi_Baseline_Pot_Produksi_Fixed_DFactory.xlsx):
 *
 *   - The pot is FIXED per event class and lives in project_classes.reward. It is never
 *     computed from points, so the total production payout of one event never exceeds it.
 *   - Each production worker's nominal = ROUND((worker total_point / total production
 *     points in the event) x pot).
 *   - The last production row absorbs the rounding remainder so the event payout equals
 *     the pot exactly (no drift from decimal rounding).
 *   - When the event has zero production points there is no contribution basis, so every
 *     reward stays 0 (the Excel flags this as "PERLU KEPUTUSAN MANUAL").
 *
 * Project managers are excluded entirely. Entertainment/VJ workers still get their points
 * recorded but are excluded from the production pot (reward 0).
 */
class PointRecordBasedOnReward
{
    use AsAction;

    protected function buildMapping(mixed $grouped, Project|Collection $project): array
    {
        $output = [];

        $projectManagerRoles = getSettingByKey('project_manager_role');
        if ($projectManagerRoles) {
            $projectManagerRoles = json_decode($projectManagerRoles, true);
        }

        $entertainmentRoles = getSettingByKey('role_as_entertainment');
        if ($entertainmentRoles) {
            $entertainmentRoles = json_decode($entertainmentRoles, true);
        }

        foreach ($grouped as $group) {
            $user = $group->first()->employee->user;
            $userRoleId = $user->roles->first()->id;
            $isProjectManager = in_array($userRoleId, $projectManagerRoles) ? true : false;
            $isEntertainmentTeam = in_array($userRoleId, $entertainmentRoles) ? true : false;

            if (! $isProjectManager) {
                $output[] = [
                    'employee_point_id' => $group->first()->employee?->singlePoint?->id ?? 0,
                    'project_id' => $project->id,
                    'total_point' => $group->count(),
                    'additional_point' => 0,
                    'prorate_point' => 0,
                    'calculated_prorate_point' => 0,
                    'employee_type' => $isEntertainmentTeam ? 'entertainment' : 'production',
                    'original_point' => $group->count(),
                    'employee_id' => $group->first()->employee->id,
                    'tasks_detail' => $group->map(function ($item) {
                        return [
                            'task_id' => $item->task->id,
                        ];
                    }),
                ];
            }
        }

        return $output;
    }

    public function handle(string|int $projectId, array $points): void
    {
        $repo = app(ProjectRepository::class);
        $employeePointRepo = app(EmployeePointRepository::class);
        $employeePointProjectRepo = app(EmployeePointProjectRepository::class);

        $project = $repo->show(
            uid: '',
            where: "id = {$projectId}",
            select: 'id,name,project_date,project_class_id',
            relation: [
                'tasks:id,project_id,name',
                'projectClass:id,name,reward',
                'taskPicHistories:id,project_id,project_task_id,employee_id',
                'taskPicHistories.employee:id,name,nickname,user_id',
                'taskPicHistories.employee.user:id,employee_id',
                'taskPicHistories.employee.singlePoint:id,employee_id',
                'taskPicHistories.task:id,name',
            ]
        );

        $grouped = $project->taskPicHistories->groupBy('employee_id')->values();

        $mapping = $this->buildMapping($grouped, $project);

        // formatting points
        $pointData = collect($points)->map(function ($point) {
            return [
                'employee_id' => getIdFromUid($point['uid'], new Employee),
                'additional_point' => $point['additional_point'],
            ];
        });

        DB::transaction(function () use ($mapping, $employeePointProjectRepo, $employeePointRepo, $project, $pointData) {
            $pot = (float) ($project->projectClass->reward ?? 0);

            // First pass: resolve every participant's employee_point row and point figures so we
            // know each worker's total_point BEFORE splitting the fixed pot across the event.
            $rows = [];
            foreach ($mapping as $data) {
                $employeePoint = $employeePointRepo->show(uid: '', where: 'employee_id = '.$data['employee_id']);
                if (! $employeePoint) {
                    $employeePoint = $employeePointRepo->store([
                        'employee_id' => $data['employee_id'],
                        'total_point' => 0,
                        'type' => $data['employee_type'],
                    ]);
                }

                // Regular point (task count) plus the additional point supplied for this employee.
                $point = count($data['tasks_detail']);
                $additionalPoint = $pointData->firstWhere('employee_id', $data['employee_id'])['additional_point'] ?? 0;
                $totalPoint = $point + $additionalPoint;

                // Link to the employee_point row we just fetched/created (buildMapping captured the
                // id from singlePoint BEFORE it existed, so first-timers would store 0 and fail the
                // FK). total_point is the FULL per-project total (point + additional_point), so it
                // always sums back to employee_points.total_point.
                $data['employee_point_id'] = $employeePoint->id;
                $data['total_point'] = $totalPoint;
                $data['additional_point'] = $additionalPoint;
                $data['original_point'] = $point;

                $rows[] = [
                    'data' => $data,
                    'employeePoint' => $employeePoint,
                    'point' => $point,
                    'additional_point' => $additionalPoint,
                    'total_point' => $totalPoint,
                ];
            }

            // Split the fixed pot across the production participants by point-share.
            $rewards = $this->distributeRewards($rows, $pot);

            foreach ($rows as $index => $row) {
                $data = $row['data'];
                $employeePoint = $row['employeePoint'];

                $pointProject = $employeePointProjectRepo->store(
                    collect($data)->except(['tasks_detail', 'employee_id', 'employee_type'])
                        ->toArray()
                );

                // One detail row per task; tasks_detail is a Collection, so createMany(array).
                $pointProject->details()->createMany(collect($data['tasks_detail'])->toArray());

                $pointProject->rewards()->create([
                    'employee_id' => $data['employee_id'],
                    'project_id' => $project->id,
                    'base_reward' => $pot,
                    'total_point' => $row['total_point'],
                    'point' => $row['point'],
                    'additional_point' => $row['additional_point'],
                    'total_reward' => $rewards[$index],
                    'project_class_name' => $project->projectClass->name,
                    'role' => 'production',
                ]);

                logging('cost reward data', [
                    'pot' => $pot,
                    'totalPoint' => $row['total_point'],
                    'point' => $row['point'],
                    'additionalPoint' => $row['additional_point'],
                    'reward' => $rewards[$index],
                ]);

                // Update employee total point
                $employeePointRepo->update(['total_point' => $employeePoint->total_point + $row['total_point']], '', 'employee_id = '.$data['employee_id']);
            }
        });
    }

    /**
     * Split a fixed pot across the production participants by point-share, with the last
     * production row absorbing the rounding remainder so the payout equals the pot exactly.
     *
     * Entertainment/VJ participants are outside the production pot and always receive 0.
     * When there is no production point to contribute, every reward stays 0.
     *
     * @param  array<int, array{data: array<string, mixed>, total_point: int}>  $rows
     * @return array<int, float> reward keyed by the same index as $rows
     */
    protected function distributeRewards(array $rows, float $pot): array
    {
        $rewards = array_fill(0, count($rows), 0.0);

        $productionIndexes = [];
        foreach ($rows as $index => $row) {
            if ($row['data']['employee_type'] === 'production') {
                $productionIndexes[] = $index;
            }
        }

        if (empty($productionIndexes) || $pot <= 0) {
            return $rewards;
        }

        $totalPoint = 0;
        foreach ($productionIndexes as $index) {
            $totalPoint += $rows[$index]['total_point'];
        }

        // No contribution basis -> mirror the Excel "PERLU KEPUTUSAN MANUAL": leave every reward 0.
        if ($totalPoint <= 0) {
            return $rewards;
        }

        $roundedSum = 0.0;
        foreach ($productionIndexes as $index) {
            $nominal = round($rows[$index]['total_point'] / $totalPoint * $pot);
            $rewards[$index] = $nominal;
            $roundedSum += $nominal;
        }

        // Last production row absorbs the rounding remainder so the event payout equals the pot.
        $lastProductionIndex = end($productionIndexes);
        $rewards[$lastProductionIndex] += $pot - $roundedSum;

        return $rewards;
    }
}
