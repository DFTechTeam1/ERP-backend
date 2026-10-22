<?php

namespace App\Services;

use App\Data\Dfengine\UserData;
use App\Enums\Production\TaskSongStatus;
use App\Enums\Production\TransferTeamStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use App\Repository\UserRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;

class DfengineService
{
    protected function getProjectManagerRoles()
    {
        return [
            BaseRole::ProjectManager->value,
            BaseRole::ProjectManagerAdmin->value,
            BaseRole::ProjectManagerEntertainment->value,
            BaseRole::AssistantProjectManger->value,
        ];
    }

    protected function getProductionRoles()
    {
        return [
            BaseRole::Production->value,
            BaseRole::Entertainment->value,
        ];
    }

    public function defineRole(User $user)
    {
        $userRole = $user->roles->first()->name;

        return [
            'isRoot' => $user->hasRole(BaseRole::Root->value),
            'isProjectManager' => in_array($userRole, $this->getProjectManagerRoles()),
            'isProduction' => in_array($userRole, $this->getProductionRoles()),
        ];
    }

    public function getUser(): UserData
    {
        $repo = app(UserRepository::class);
        $user = $repo->detail(Auth::id(), select: 'id,employee_id,email', relation: ['employee:id,boss_id,name']);
        $defineRole = $this->defineRole($user);

        return new UserData(
            user: $user,
            isRoot: $defineRole['isRoot'],
            isProjectManager: $defineRole['isProjectManager'],
            isProduction: $defineRole['isProduction']
        );
    }

    protected function entertainmentRoles(): array
    {
        return [
            BaseRole::Entertainment->value,
            BaseRole::ProjectManagerEntertainment->value,
        ];
    }

    protected function productionRoles(): array
    {
        return [
            BaseRole::Production->value,
            BaseRole::LeadModeller->value,
            BaseRole::ProjectManager->value,
            BaseRole::ProjectManagerAdmin->value,
            BaseRole::AssistantProjectManger->value,
        ];
    }

    public function getProjectFilter()
    {
        $userData = $this->getUser();

        $isForProduction = (bool) in_array($userData->user->roles->first()->name, $this->productionRoles());
        $isForEntertainment = (bool) in_array($userData->user->roles->first()->name, $this->entertainmentRoles());

        $conditions = [];

        if ($isForProduction) {
            $conditions = $this->getProductionProjectFilter($userData);
        } elseif ($isForEntertainment) {
            $conditions = $this->getEntertainmentProjectFilter($userData);
        }

        return $conditions;
    }

    public function getEntertainmentProjectFilter(UserData $userData): array
    {
        $whereGroup = [
            function (Builder $query) use ($userData) {
                $query->whereExists(function (Builder $sub) use ($userData) {
                    $sub->selectRaw('1')
                        ->from('entertainment_task_songs as ets')
                        ->whereColumn('ets.project_id', 'projects.id')
                        ->where('status', TaskSongStatus::OnProgress->value)
                        ->where('employee_id', $userData->user->employee_id);
                });
            },
        ];

        return [
            'whereGroup' => $whereGroup,
        ];
    }

    public function getProductionProjectFilter(UserData $userData): array
    {
        $isProjectManager = $userData->isProjectManager;
        $isProduction = $userData->isProduction;

        $whereGroup = [];
        $whereExists = null;
        if ($isProduction) {
            // Only get boss projects
            $bossId = $userData->user?->employee?->boss_id;
            if ($bossId) {
                $whereExists = function (Builder $subQuery) use ($bossId) {
                    $subQuery->selectRaw('1')
                        ->from('project_person_in_charges as ppic')
                        ->whereColumn('ppic.project_id', 'projects.id')
                        ->where('ppic.pic_id', $bossId);
                };
            }
        }
        if ($isProjectManager && $userData->user->employee) {
            $whereExists = function (Builder $subQuery) use ($userData) {
                $subQuery->selectRaw('1')
                    ->from('project_person_in_charges as ppic')
                    ->whereColumn('ppic.project_id', 'projects.id')
                    ->where('ppic.pic_id', $userData->user->employee->id);
            };
        }

        $whereGroup[] = function ($query) use ($whereExists) {
            if ($whereExists) {
                $query->whereExists($whereExists)
                    ->orWhereExists(function (Builder $sub) {
                        $sub->selectRaw('1')
                            ->from('transfer_team_members as ttm')
                            ->whereColumn('ttm.project_id', 'projects.id')
                            ->where('ttm.status', TransferTeamStatus::Approved->value);
                    });
            } else {
                $query->whereExists(function (Builder $subQuery) {
                    $subQuery->selectRaw('1')
                        ->from('transfer_team_members as ttm')
                        ->whereColumn('ttm.project_id', 'projects.id')
                        ->where('ttm.status', TransferTeamStatus::Approved->value);
                });
            }
        };

        return [
            'whereGroup' => $whereGroup,
        ];
    }
}
