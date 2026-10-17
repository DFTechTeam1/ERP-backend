<?php

use App\Models\User;
use App\Services\RoleService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/**
 * RoleService + RoleController (role CRUD on the `sanctum` guard).
 *
 * Service methods return the generalResponse envelope; the HTTP endpoints live under the
 * `auth.session` group at /api/roles (apiResource) plus /api/roles/getAll and /api/roles/bulk.
 *
 * All roles/permissions are created on the `sanctum` guard because RoleRepository::show() resolves
 * via Role::findById($id, 'sanctum'). The acting user always holds a role (update() reads
 * auth()->user()->roles[0]). The Spatie permission cache is flushed per test so grants created in
 * one test never leak into the next.
 */
function roleSvc(): RoleService
{
    return new RoleService;
}

function sanctumRole(string $name): Role
{
    return Role::create(['name' => $name, 'guard_name' => 'sanctum']);
}

function sanctumPerm(string $name): Permission
{
    return Permission::create(['name' => $name, 'guard_name' => 'sanctum']);
}

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actor = User::factory()->create();
    $this->actorRole = sanctumRole('actor-role');
    $this->actor->assignRole($this->actorRole);
    actingAs($this->actor);
});

describe('list', function () {
    it('returns a paginated, de-duplicated list with the total count and a success message', function () {
        sanctumRole('alpha');
        sanctumRole('beta');

        request()->merge(['limit' => 10, 'page' => 1]);
        $response = roleSvc()->list();

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe(__('global.success'))
            ->and($response['data']['totalData'])->toBe(Role::count());

        $names = collect($response['data']['paginated'])->pluck('name');
        expect($names)->toContain('alpha', 'beta', 'actor-role');
    });

    it('filters by the name query param', function () {
        sanctumRole('Editor');
        sanctumRole('Viewer');

        request()->merge(['limit' => 10, 'page' => 1, 'name' => 'Edit']);
        $response = roleSvc()->list();

        $names = collect($response['data']['paginated'])->pluck('name');
        expect($names)->toContain('Editor')
            ->and($names)->not->toContain('Viewer')
            ->and($response['data']['totalData'])->toBe(1);
    });
});

describe('getAll', function () {
    it('returns every role as an option with the name de-slugified', function () {
        $role = sanctumRole('content_writer');

        $response = roleSvc()->getAll();

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe(__('global.success'));

        $option = collect($response['data'])->firstWhere('value', $role->id);
        expect($option['title'])->toBe('content_writer')   // raw name
            ->and($option['name'])->toBe('content writer'); // underscores -> spaces
    });
});

describe('store', function () {
    it('creates a role on the sanctum guard and attaches its permissions', function () {
        $permA = sanctumPerm('edit_user');
        $permB = sanctumPerm('delete_user');

        $response = roleSvc()->store(['name' => 'Editor', 'permissions' => [$permA->id, $permB->id]]);

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe(__('global.successCreateRole'));

        assertDatabaseHas('roles', ['name' => 'Editor', 'guard_name' => 'sanctum']);

        $role = Role::findByName('Editor', 'sanctum');
        assertDatabaseHas('role_has_permissions', ['role_id' => $role->id, 'permission_id' => $permA->id]);
        assertDatabaseHas('role_has_permissions', ['role_id' => $role->id, 'permission_id' => $permB->id]);
    });

    it('rolls back and reports an error when the role name already exists', function () {
        sanctumRole('Duplicate');
        $perm = sanctumPerm('some_perm');

        // Spatie rejects a second role with the same name on the same guard.
        $response = roleSvc()->store(['name' => 'Duplicate', 'permissions' => [$perm->id]]);

        expect($response['error'])->toBeTrue();
        // only the original role exists (the transaction rolled back)
        expect(Role::where('name', 'Duplicate')->where('guard_name', 'sanctum')->count())->toBe(1);
    });
});

describe('update', function () {
    it('replaces the permission set and flags no logout for an unrelated actor', function () {
        $role = sanctumRole('Manager');
        $permOld = sanctumPerm('old_perm');
        $permNew = sanctumPerm('new_perm');
        $role->givePermissionTo($permOld);

        $response = roleSvc()->update(['name' => 'Manager', 'permissions' => [$permNew->id]], $role->id);

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe(__('global.successUpdateRole'))
            ->and($response['data']['has_to_logout'])->toBeFalse();

        // old permission revoked, new one granted
        assertDatabaseMissing('role_has_permissions', ['role_id' => $role->id, 'permission_id' => $permOld->id]);
        assertDatabaseHas('role_has_permissions', ['role_id' => $role->id, 'permission_id' => $permNew->id]);
    });

    it('flags has_to_logout when the actor is editing their own role', function () {
        $permNew = sanctumPerm('new_perm');

        // the actor holds actor-role, so editing it must ask them to re-login
        $response = roleSvc()->update(['name' => 'actor-role', 'permissions' => [$permNew->id]], $this->actorRole->id);

        expect($response['error'])->toBeFalse()
            ->and($response['data']['has_to_logout'])->toBeTrue();
    });
});

describe('show', function () {
    it('returns the role as a DetailRoleData DTO with its permissions', function () {
        $role = sanctumRole('Supervisor');
        $perm = sanctumPerm('view_report');
        $role->givePermissionTo($perm);

        $response = roleSvc()->show($role->id);

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe(__('global.success'))
            ->and($response['data']['uid'])->toBe((string) $role->id)
            ->and($response['data']['name'])->toBe('Supervisor');

        $permission = collect($response['data']['permissions'])->firstWhere('name', 'view_report');
        expect($permission)->not->toBeNull()
            ->and($permission['uid'])->toBe((string) $perm->id);
    });
});

describe('destroy', function () {
    it('deletes a role that no user holds', function () {
        $role = sanctumRole('Temporary');

        $response = roleSvc()->destroy($role->id);

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe(__('global.successDeleteRole'));
        assertDatabaseMissing('roles', ['id' => $role->id]);
    });

    it('refuses to delete a role that is still assigned to a user', function () {
        $role = sanctumRole('InUse');
        User::factory()->create()->assignRole($role);

        $response = roleSvc()->destroy($role->id);

        expect($response['error'])->toBeTrue();
        assertDatabaseHas('roles', ['id' => $role->id]); // untouched
    });
});

describe('bulkDelete', function () {
    it('deletes several unused roles at once', function () {
        $a = sanctumRole('bulk-a');
        $b = sanctumRole('bulk-b');

        $response = roleSvc()->bulkDelete([$a->id, $b->id]);

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe(__('global.successDeleteRole'));
        assertDatabaseMissing('roles', ['id' => $a->id]);
        assertDatabaseMissing('roles', ['id' => $b->id]);
    });

    it('aborts the whole batch when any role is still assigned to a user', function () {
        $free = sanctumRole('bulk-free');
        $used = sanctumRole('bulk-used');
        User::factory()->create()->assignRole($used);

        $response = roleSvc()->bulkDelete([$free->id, $used->id]);

        expect($response['error'])->toBeTrue();
        // nothing deleted - the check runs before any delete
        assertDatabaseHas('roles', ['id' => $free->id]);
        assertDatabaseHas('roles', ['id' => $used->id]);
    });
});

/**
 * -----------------------------------------------------------------------------
 * End-to-end: /api/roles* (auth.session guarded)
 * -----------------------------------------------------------------------------
 */
describe('http endpoints', function () {
    it('[e2e] lists roles', function () {
        sanctumRole('http-alpha');

        $response = getJson('/api/roles?limit=10&page=1');

        $response->assertSuccessful();
        expect($response->json('data.paginated'))->not->toBeNull()
            ->and($response->json('data'))->toHaveKey('totalData');
    });

    it('[e2e] returns all roles as options', function () {
        getJson('/api/roles/getAll')->assertSuccessful();
    });

    it('[e2e] creates a role', function () {
        $perm = sanctumPerm('http_edit');

        $response = postJson('/api/roles', ['name' => 'HttpEditor', 'permissions' => [$perm->id]]);

        $response->assertSuccessful();
        expect($response->json('message'))->toBe(__('global.successCreateRole'));
        assertDatabaseHas('roles', ['name' => 'HttpEditor', 'guard_name' => 'sanctum']);
    });

    it('[e2e] rejects a role create with a missing field', function () {
        // name missing -> failedValidation returns code 400 (not 422)
        postJson('/api/roles', ['permissions' => [1]])->assertStatus(400);
    });

    it('[e2e] shows a role', function () {
        $role = sanctumRole('HttpShow');

        getJson("/api/roles/{$role->id}")
            ->assertSuccessful()
            ->assertJsonPath('data.name', 'HttpShow');
    });

    it('[e2e] updates a role', function () {
        $role = sanctumRole('HttpUpdate');
        $perm = sanctumPerm('http_update_perm');

        $response = putJson("/api/roles/{$role->id}", ['name' => 'HttpUpdated', 'permissions' => [$perm->id]]);

        $response->assertSuccessful();
        expect($response->json('message'))->toBe(__('global.successUpdateRole'));
        assertDatabaseHas('role_has_permissions', ['role_id' => $role->id, 'permission_id' => $perm->id]);
    });

    it('[e2e] deletes a role', function () {
        $role = sanctumRole('HttpDelete');

        deleteJson("/api/roles/{$role->id}")->assertSuccessful();
        assertDatabaseMissing('roles', ['id' => $role->id]);
    });

    it('[e2e] bulk-deletes roles from a list of {uid}', function () {
        $a = sanctumRole('http-bulk-a');
        $b = sanctumRole('http-bulk-b');

        postJson('/api/roles/bulk', ['ids' => [['uid' => $a->id], ['uid' => $b->id]]])
            ->assertSuccessful();

        assertDatabaseMissing('roles', ['id' => $a->id]);
        assertDatabaseMissing('roles', ['id' => $b->id]);
    });

    it('[e2e] rejects an unauthenticated request', function () {
        auth()->forgetGuards();

        getJson('/api/roles')->assertUnauthorized();
    });
});
