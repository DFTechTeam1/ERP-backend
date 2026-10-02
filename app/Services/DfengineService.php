<?php

namespace App\Services;

use App\Data\Dfengine\UserData;
use App\Enums\System\BaseRole;
use App\Models\User;
use App\Repository\UserRepository;
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
}
