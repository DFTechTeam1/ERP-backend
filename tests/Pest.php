<?php

use App\Enums\System\BaseRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hrd\Models\Employee;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests are bound to Tests\TestCase (which guards against running
| against a non-test database) and use RefreshDatabase. Unit tests stay on
| the lightweight PHPUnit TestCase with no database access.
|
*/

pest()
    ->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Project-wide test helpers. Keep this lean — add helpers here only when they
| are reused across multiple test files.
|
*/

/**
 * Create a user, assign a role (creating it if missing), optionally attach
 * permissions and a linked employee, and return the user ready for actingAs().
 *
 * @param  array<int, string>  $permissions
 */
function initAuthenticateUser(
    array $permissions = [],
    bool $withEmployee = false,
    string $roleName = BaseRole::Root->value,
    ?object $user = null
): User {
    if (! $withEmployee) {
        if (! $user) {
            $user = User::factory()->create();
        }
    } else {
        $employee = Employee::factory()->withUser()->create();

        $user = User::where('employee_id', $employee->id)->first();
    }

    $role = Role::firstOrCreate(
        ['name' => $roleName, 'guard_name' => 'sanctum'],
    );

    foreach ($permissions as $permissionName) {
        Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'sanctum']);
        $user->givePermissionTo($permissionName);
    }

    $user->assignRole($role);

    return $user;
}
