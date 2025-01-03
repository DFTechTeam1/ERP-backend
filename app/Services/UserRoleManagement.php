<?php

namespace App\Services;

class UserRoleManagement {
    public function isProductionRole()
    {
        $productionRoles = json_decode(getSettingByKey('production_staff_role'), true);
        $roles = auth()->user()->roles;

        return $productionRoles ? in_array($roles[0]->id, $productionRoles) : false;
    }

    public function isEntertainmentRole()
    {
        return auth()->user()->hasRole('entertainment');
    }
}