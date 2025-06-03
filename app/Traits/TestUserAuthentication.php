<?php

namespace App\Traits;

use App\Models\User;
use App\Repository\RoleRepository;
use App\Repository\UserLoginHistoryRepository;
use App\Repository\UserRepository;
use App\Services\GeneralService;
use App\Services\RoleService;
use App\Services\UserService;
use Modules\Company\Models\IndonesiaCity;
use Modules\Company\Models\IndonesiaDistrict;
use Modules\Company\Models\IndonesiaVillage;
use Modules\Company\Models\Province;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeeRepository;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

trait TestUserAuthentication
{
    public function auth(string $roleName = 'testing role', string $permissionName = 'detail_project')
    {
        $province = Province::factory()->count(1)->create();
        $city = IndonesiaCity::factory()
            ->count(1)
            ->state(['province_code' => $province[0]->code])
            ->create();
        $district = IndonesiaDistrict::factory()
            ->count(1)
            ->state(['city_code' => $city[0]->code])
            ->create();
        $village = IndonesiaVillage::factory()
            ->count(1)
            ->state(['district_code' => $district[0]->code])
            ->create();

        $employee = Employee::factory()
            ->count(1)
            ->state([
                'province_id' => $province[0]->code,
                'city_id' => $city[0]->code,
                'district_id' => $district[0]->code,
                'village_id' => $village[0]->code,
            ])
            ->create();
        $role = Role::create(['name' => $roleName, 'guard_name' => 'sanctum']);
        $permission = Permission::create(['name' => $permissionName, 'guard_name' => 'sanctum']);
        $role->givePermissionTo($permission);

        $users = User::factory()->count(1)->create(['employee_id' => $employee[0]->id]);
        $user = $users->firstOrFail();
        $user->assignRole($role);

        $employee[0]->user_id = $users[0]->id;
        $employee[0]->save();

        return [
            'employee' => $employee,
            'user' => $user,
        ];
    }

    public function getToken($user)
    {
        $service = new UserService(
            new UserRepository,
            new EmployeeRepository,
            new RoleRepository,
            new UserLoginHistoryRepository,
            new GeneralService,
            new RoleService
        );

        return $service->login(validated: [
            'email' => $user->email,
            'remember_me' => false,
            'password' => 'password',
        ], unitTesting: true);
    }
}
