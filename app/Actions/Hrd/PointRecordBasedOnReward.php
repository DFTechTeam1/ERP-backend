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

    public function handle(string|int $projectId, array $points)
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

                $pointProject = $employeePointProjectRepo->store(
                    collect($data)->except(['tasks_detail', 'employee_id', 'employee_type'])
                        ->toArray()
                );

                // One detail row per task; tasks_detail is a Collection, so createMany(array).
                $pointProject->details()->createMany(collect($data['tasks_detail'])->toArray());

                // Record rewards
                $baseReward = $project->projectClass->reward;
                $totalReward = $baseReward * $totalPoint;

                $pointProject->rewards()->create([
                    'employee_id' => $data['employee_id'],
                    'project_id' => $project->id,
                    'base_reward' => $baseReward,
                    'total_point' => $totalPoint,
                    'point' => $point,
                    'additional_point' => $additionalPoint,
                    'total_reward' => $totalReward,
                    'project_class_name' => $project->projectClass->name,
                ]);

                // Update employee total point
                $employeePointRepo->update(['total_point' => $employeePoint->total_point + $totalPoint], '', 'employee_id = '.$data['employee_id']);
            }
        });
    }
}
