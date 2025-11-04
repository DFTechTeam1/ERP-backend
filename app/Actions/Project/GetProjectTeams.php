<?php

namespace App\Actions\Project;

use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Repository\ProjectTaskPicHistoryRepository;
use Modules\Production\Repository\TransferTeamMemberRepository;

class GetProjectTeams
{
    use AsAction;

    public function handle(object $project, bool $allSpecialPosition = false)
    {
        $employeeRepo = new EmployeeRepository;
        $transferTeamRepo = new TransferTeamMemberRepository;
        $taskPicHistory = new ProjectTaskPicHistoryRepository;

        $where = '';
        $pics = [];
        $teams = [];
        $picIds = [];
        $picUids = [];

        if ($productionPositions = json_decode(getSettingByKey('position_as_production'), true)) {
            $productionPositions = collect($productionPositions)->map(function ($item) {
                return getIdFromUid($item, new \Modules\Company\Models\PositionBackup);
            })->toArray();
        }

        foreach ($project->personInCharges as $key => $pic) {
            $pics[] = $pic->employee->name.'('.$pic->employee->employee_id.')';
            $picIds[] = $pic->pic_id;
            $picUids[] = $pic->employee->uid;

            // check persion in charge role
            // if Assistant, then get teams based his team and his boss team
            $userPerson = \App\Models\User::selectRaw('id')->where('employee_id', $pic->employee->id)
                ->first();
            if ($userPerson->hasRole('assistant manager')) {
                // get boss team
                if ($pic->employee->boss_id) {
                    array_push($picIds, $pic->employee->boss_id);
                    array_push($picUids, $pic->employee->uid);
                }
            }
        }

        $picIds = array_values(array_unique($picIds));
        $picUids = array_values(array_unique($picUids));

        // get special position that will be append on each project manager team members
        $specialPosition = getSettingByKey('special_production_position');
        $leadModeller = getSettingByKey('lead_3d_modeller');

        $specialEmployee = [];
        $specialIds = [];
        if ($specialPosition) {
            $specialPosition = getIdFromUid($specialPosition, new \Modules\Company\Models\PositionBackup);
            $whereSpecial = "position_id = {$specialPosition}";
            $isLeadModeller = false;
            if ($leadModeller != null && $leadModeller != '' && $leadModeller != 'null' && !$allSpecialPosition) {
                $leadModeller = getIdFromUid($leadModeller, new Employee);
                $whereSpecial = "id = {$leadModeller}";
                $isLeadModeller = true;
            }

            $specialEmployee = $employeeRepo->list('id,uid,name,nickname,email,position_id', $whereSpecial, ['position:id,name'])->toArray();

            $specialEmployee = collect($specialEmployee)->map(function ($employee) use ($isLeadModeller) {
                $employee['loan'] = false;
                $employee['is_lead_modeller'] = $isLeadModeller;

                return $employee;
            })->toArray();

            $specialIds = collect($specialEmployee)->pluck('id')->toArray();
        }

        // get another teams from approved transfer team
        $user = auth()->user();
        $roles = $user->roles;
        $roleId = $roles[0]->id;
        $superUserRole = getSettingByKey('super_user_role');
        $transferCondition = 'status = '.\App\Enums\Production\TransferTeamStatus::Approved->value.' and project_id = '.$project->id.' and is_entertainment = 0';
        if ($roleId != $superUserRole) {
            $transferCondition .= ' and requested_by = '.$user->employee_id;
        }

        if (count($picIds) > 0) {
            $picId = implode(',', $picIds);
            $employeeCondition = "boss_id IN ($picId)";
        } else {
            $employeeCondition = 'boss_id IN (0)';
        }

        $employeeCondition .= ' and status != '.\App\Enums\Employee\Status::Inactive->value;

        if (count($specialIds) > 0) {
            $specialId = implode(',', $specialIds);
            $transferCondition .= " and employee_id NOT IN ($specialId)";
            $employeeCondition .= " and id NOT IN ($specialId)";
        }

        if ($leadModeller && $specialPosition) {
            $employeeCondition .= " and position_id NOT IN ({$specialPosition})";
        }

        $transfers = $transferTeamRepo->list('id,employee_id', $transferCondition, ['employee:id,name,nickname,uid,email,employee_id,position_id', 'employee.position:id,name']);

        $transfers = collect((object) $transfers)->map(function ($transfer) {
            return [
                'id' => $transfer->employee->id,
                'uid' => $transfer->employee->uid,
                'email' => $transfer->employee->email,
                'nickname' => $transfer->employee->nickname,
                'name' => $transfer->employee->name,
                'position' => $transfer->employee->position,
                'loan' => true,
                'last_update' => '-',
                'current_task' => '-',
                'image' => asset('images/user.png'),
            ];
        })->toArray();

        if ($productionPositions) {
            $productionPositions = implode(',', $productionPositions);
            $employeeCondition .= " and position_id in ({$productionPositions})";
        }

        $teams = $employeeRepo->list(
            'id,uid,name,email,nickname,position_id',
            $employeeCondition,
            ['position:id,name'],
            '',
            '',
            [
                [
                    'relation' => 'position',
                    'query' => "(LOWER(name) not like '%project manager%')",
                ],
                [
                    'relation' => 'position.division',
                    'query' => "LOWER(name) like '%production%'",
                ],
            ]
        );

        if (count($teams) > 0) {
            $teams = collect($teams)->map(function ($team) {
                $team['last_update'] = '-';
                $team['current_task'] = '-';
                $team['loan'] = false;
                $team['image'] = asset('images/user.png');

                return $team;
            })->toArray();

            // THIS CAUSE PM WHO DO NOT HAVE ANY TEAM MEMBER CANNOT SEE TRANSFER AND SPECIAL EMPLOYEE
            // SHO THIS SHOULD BE RUNNING OUTSIDE OF THIS 'IF' CONDITION
            // $teams = collect($teams)->merge($transfers)->toArray();

            // $teams = collect($teams)->merge($specialEmployee)->toArray();
        }

        $teams = collect($teams)->merge($transfers)->toArray();

        $teams = collect($teams)->merge($specialEmployee)->toArray();

        // get task on selected project
        $outputTeam = [];
        foreach ($teams as $key => $team) {
            $task = $taskPicHistory->list('id', 'project_id = '.$project->id.' and employee_id = '.$team['id'])->count();

            $outputTeam[$key] = $team;
            $outputTeam[$key]['total_task'] = $task;
        }

        // get entertainment teams
        $entertain = $transferTeamRepo->list(
            'id,employee_id,requested_by,alternative_employee_id',
            'project_id = '.$project->id.' and is_entertainment = 1 and employee_id is not null',
            ['employee:id,uid,name,email,position_id', 'employee.position:id,name']
        );

        $outputEntertain = collect((object) $entertain)->map(function ($item) {
            return [
                'id' => $item->employee->id,
                'uid' => $item->employee->uid,
                'name' => $item->employee->name,
                'total_task' => 0,
                'loan' => true,
                'position' => $item->employee->position,
            ];
        })->toArray();

        return [
            'pics' => $pics,
            'teams' => $outputTeam,
            'picUids' => $picUids,
            'entertain' => $outputEntertain,
        ];
    }
}
