<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSetting extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Schema::disableForeignKeyConstraints();

        $permissions = Permission::all();

        $roles = Role::all();
        foreach ($roles as $role) {
            $role->syncPermissions([]);
        }

        $users = \App\Models\User::all();
        foreach ($users as $user) {
            $user->syncRoles([]);
        }

        Permission::truncate();
        Role::truncate();
        DB::table('role_has_permissions')->truncate();
        DB::table('model_has_roles')->truncate();
        DB::table('model_has_permissions')->truncate();

        $this->seedPermissions();

        $this->seedRoles();

        Schema::enableForeignKeyConstraints();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function seedRoles()
    {
        $roles = [
            ['name' => $this->getRootRole(), 'permissions' => $this->getRolePermissions($this->getRootRole())],
            ['name' => $this->getDirectorRole(), 'permissions' => $this->getRolePermissions($this->getDirectorRole())],
            ['name' => $this->getMarketingRole(), 'permissions' => $this->getRolePermissions($this->getMarketingRole())],
            ['name' => $this->getProductionRole(), 'permissions' => $this->getRolePermissions($this->getProductionRole())],
            ['name' => $this->getProjectManagerRole(), 'permissions' => $this->getRolePermissions($this->getProjectManagerRole())],
            ['name' => $this->getProjectManagerAdminRole(), 'permissions' => $this->getRolePermissions($this->getProjectManagerAdminRole())],
            ['name' => $this->getProjectManagerEntertainmentRole(), 'permissions' => $this->getRolePermissions($this->getProjectManagerEntertainmentRole())],
            ['name' => $this->getEntertainmentRole(), 'permissions' => $this->getRolePermissions($this->getEntertainmentRole())],
            ['name' => $this->getItSupportRole(), 'permissions' => $this->getRolePermissions($this->getItSupportRole())],
            ['name' => $this->getHrdRole(), 'permissions' => $this->getRolePermissions($this->getHrdRole())],
            ['name' => $this->getFinanceRole(), 'permissions' => $this->getRolePermissions($this->getFinanceRole())],
            ['name' => $this->getRegularRole(), 'permissions' => $this->getRolePermissions($this->getRegularRole())],
        ];

        foreach ($roles as $role) {
            $check = DB::table('roles')
                ->where('name', $role['name'])
                ->first();

            if (!$check) {
                $roleData = Role::create(collect($role)->except('permissions')->toArray());

                $roleData->syncPermissions($role['permissions']);
            }
        }

        $this->command->info('All role and permissions is updated');
    }

    protected function getRolePermissions(string $roleName)
    {
        switch ($roleName) {
            case 'root':
                $roleKey = $this->getRootRole();
                break;

            case 'director':
                $roleKey = $this->getDirectorRole();
                break;

            case 'marketing':
                $roleKey = $this->getMarketingRole();
                break;

            case 'production':
                $roleKey = $this->getProductionRole();
                break;

            case 'project manager':
                $roleKey = $this->getProjectManagerRole();
                break;

            case 'project manager admin':
                $roleKey = $this->getProjectManagerAdminRole();
                break;

            case 'project manager entertainment':
                $roleKey = $this->getProjectManagerEntertainmentRole();
                break;

            case 'entertainment':
                $roleKey = $this->getEntertainmentRole();
                break;

            case 'it support':
                $roleKey = $this->getItSupportRole();
                break;

            case 'hrd':
                $roleKey = $this->getHrdRole();
                break;

            case 'finance':
                $roleKey = $this->getFinanceRole();
                break;

            case 'regular employee':
                $roleKey = $this->getRegularRole();
                break;
            
            default:
                $roleKey = $this->getRootRole();
                break;
        }

        $permissions = $this->getAllPermissions();

        $selected = [];
        foreach ($permissions as $key => $permission) {
            if (isset($permission['used'])) {
                if (in_array($roleKey, $permission['used'])) {
                    $selected[] = $permission['name'];
                }
            }
        }

        return $selected;
    }

    protected function getRootRole()
    {
        return 'root';
    }

    protected function getMarketingRole()
    {
        return 'marketing';
    } 

    protected function getDirectorRole()
    {
        return 'director';
    }

    protected function getProductionRole()
    {
        return 'production';
    }

    protected function getEntertainmentRole()
    {
        return 'entertainment';
    }

    protected function getProjectManagerAdminRole()
    {
        return 'project manager admin';
    }

    protected function getItSupportRole()
    {
        return 'it support';
    }

    protected function getHrdRole()
    {
        return 'hrd';
    }

    protected function getFinanceRole()
    {
        return 'finance';
    }

    protected function getRegularRole()
    {
        return 'regulare employee';
    }

    protected function getProjectManagerRole()
    {
        return 'project manager';
    }

    protected function getProjectManagerEntertainmentRole()
    {
        return 'project manager entertainment';
    }

    protected function dashboardPermission()
    {
        return [
            ['name' => 'dashboard_access', 'group' => 'dashboard', 'used' => [
                $this->getProjectManagerEntertainmentRole(),
                $this->getProjectManagerRole(),
                $this->getRegularRole(),
                $this->getFinanceRole(),
                $this->getHrdRole(),
                $this->getItSupportRole(),
                $this->getProjectManagerAdminRole(),
                $this->getEntertainmentRole(),
                $this->getProductionRole(),
                $this->getDirectorRole(),
                $this->getMarketingRole(),
                $this->getRootRole(),
            ]],
        ];
    }

    protected function userManagementPermission()
    {
        return [
            ['name' => 'create_user', 'group' => 'user_management', 'used' => [
                $this->getRootRole(),
                $this->getHrdRole(),
                $this->getDirectorRole(),
            ]],
            ['name' => 'list_user', 'group' => 'user_management', 'used' => [
                $this->getRootRole(),
                $this->getHrdRole(),
                $this->getDirectorRole(),
            ]],
            ['name' => 'edit_user', 'group' => 'user_management', 'used' => [
                $this->getRootRole(),
                $this->getHrdRole(),
                $this->getDirectorRole(),
            ]],
            ['name' => 'detail_user', 'group' => 'user_management', 'used' => [
                $this->getRootRole(),
                $this->getHrdRole(),
                $this->getDirectorRole(),
            ]],
            ['name' => 'delete_user', 'group' => 'user_management', 'used' => [
                $this->getRootRole(),
                $this->getHrdRole(),
                $this->getDirectorRole(),
            ]],
            ['name' => 'create_role', 'group' => 'user_management', 'used' => [
                $this->getRootRole(),
                $this->getHrdRole(),
                $this->getDirectorRole(),
            ]],
            ['name' => 'edit_role', 'group' => 'user_management', 'used' => [
                $this->getRootRole(),
                $this->getHrdRole(),
                $this->getDirectorRole(),
            ]],
            ['name' => 'list_role', 'group' => 'user_management', 'used' => [
                $this->getRootRole(),
                $this->getHrdRole(),
                $this->getDirectorRole(),
            ]],
            ['name' => 'delete_role', 'group' => 'user_management', 'used' => [
                $this->getRootRole(),
                $this->getHrdRole(),
                $this->getDirectorRole(),
            ]],
            ['name' => 'list_performance_report', 'group' => 'user_management', 'used' => [
                $this->getRootRole(),
                $this->getHrdRole(),
                $this->getDirectorRole(),
            ]],
        ];
    }

    protected function employeePermission()
    {
        return [
            ['name' => 'create_employee', 'group' => 'employee', 'used' => [
                $this->getRootRole(),
                $this->getHrdRole(),
                $this->getDirectorRole(),
            ]],
            ['name' => 'edit_employee', 'group' => 'employee', 'used' => [
                $this->getRootRole(),
                $this->getHrdRole(),
                $this->getDirectorRole(),
            ]],
            ['name' => 'detail_employee', 'group' => 'employee', 'used' => [
                $this->getRootRole(),
                $this->getHrdRole(),
                $this->getDirectorRole(),
            ]],
            ['name' => 'delete_employee', 'group' => 'employee', 'used' => [
                $this->getRootRole(),
                $this->getHrdRole(),
                $this->getDirectorRole(),
            ]],
            ['name' => 'list_employee', 'group' => 'employee', 'used' => [
                $this->getRootRole(),
                $this->getHrdRole(),
                $this->getDirectorRole(),
            ]],
            ['name' => 'invite_employee_as_user', 'group' => 'employee', 'used' => [
                $this->getRootRole(),
                $this->getHrdRole(),
                $this->getDirectorRole(),
            ]],
            ['name' => 'import_employee', 'group' => 'employee', 'used' => [
                $this->getRootRole(),
                $this->getHrdRole(),
                $this->getDirectorRole(),
            ]],
            ['name' => 'export_employee', 'group' => 'employee', 'used' => [
                $this->getRootRole(),
                $this->getHrdRole(),
                $this->getDirectorRole(),
            ]],
        ];
    }

    protected function masterPermission()
    {
        return [
            ['name' => 'list_division', 'group' => 'master', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getHrdRole(),
            ]],
            ['name' => 'create_division', 'group' => 'master', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getHrdRole(),
            ]],
            ['name' => 'edit_division', 'group' => 'master', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getHrdRole(),
            ]],
            ['name' => 'delete_division', 'group' => 'master', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getHrdRole(),
            ]],
            ['name' => 'list_position', 'group' => 'master', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getHrdRole(),
            ]],
            ['name' => 'create_position', 'group' => 'master', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getHrdRole(),
            ]],
            ['name' => 'edit_position', 'group' => 'master', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getHrdRole(),
            ]],
            ['name' => 'delete_position', 'group' => 'master', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getHrdRole(),
            ]],
            ['name' => 'list_project_class', 'group' => 'master', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
            ]],
            ['name' => 'create_project_class', 'group' => 'master', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
            ]],
            ['name' => 'edit_project_class', 'group' => 'master', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
            ]],
            ['name' => 'delete_project_class', 'group' => 'master', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
            ]],
        ];
    }

    protected function inventoriesPermission()
    {
        return [
            ['name' => 'list_supplier', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'create_supplier', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'edit_supplier', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'delete_supplier', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'list_brand', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'create_brand', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'edit_brand', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'delete_brand', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'list_unit', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'create_unit', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'edit_unit', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'delete_unit', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'list_inventory_type', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'create_inventory_type', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'edit_inventory_type', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'delete_inventory_type', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'list_inventory', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'create_inventory', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'edit_inventory', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'delete_inventory', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'import_inventory', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'request_inventory', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'create_service_inventory', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'reject_request_equipment', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'accept_request_equipment', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'detail_request_equipment', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'list_custom_inventory', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'create_custom_inventory', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'edit_custom_inventory', 'group' => 'inventories', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getItSupportRole(),
            ]],
        ];
    }

    protected function settingPermission()
    {
        return [
            ['name' => 'list_setting', 'group' => 'setting', 'used' => [$this->getRootRole(),
                $this->getDirectorRole()]],
            ['name' => 'setting_general', 'group' => 'setting', 'used' => [$this->getRootRole(),
                $this->getDirectorRole()]],
            ['name' => 'setting_kanban', 'group' => 'setting', 'used' => [$this->getRootRole(),
                $this->getDirectorRole()]],
        ];
    }

    protected function taskPermission()
    {
        return [
            ['name' => 'add_task', 'group' => 'task', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
            ]],
            ['name' => 'list_task', 'group' => 'task', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
                $this->getProductionRole(),
                $this->getEntertainmentRole(),
                $this->getProjectManagerEntertainmentRole(),
            ]],
            ['name' => 'edit_task_name', 'group' => 'task', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
            ]],
            ['name' => 'edit_task_description', 'group' => 'task', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
            ]],
            ['name' => 'delete_task', 'group' => 'task', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
            ]],
            ['name' => 'assign_task_pic', 'group' => 'task', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
            ]],
            ['name' => 'add_task_deadline', 'group' => 'task', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
                $this->getProductionRole(),
                $this->getEntertainmentRole(),
                $this->getProjectManagerEntertainmentRole(),
            ]],
            ['name' => 'add_task_attachment', 'group' => 'task', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
                $this->getProductionRole(),
                $this->getEntertainmentRole(),
                $this->getProjectManagerEntertainmentRole(),
            ]],
            ['name' => 'task_log_access', 'group' => 'task', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
            ]],
            ['name' => 'proof_of_work_list', 'group' => 'task', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
                $this->getProductionRole(),
                $this->getEntertainmentRole(),
                $this->getProjectManagerEntertainmentRole(),
            ]],
        ];
    }

    protected function projectPermission()
    {
        return [
            ['name' => 'list_project', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
                $this->getProjectManagerEntertainmentRole(),
                $this->getEntertainmentRole(),
                $this->getProductionRole(),
            ]],
            ['name' => 'create_project', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getMarketingRole(),
                $this->getProjectManagerAdminRole(),
            ]],
            ['name' => 'edit_project', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getMarketingRole(),
                $this->getProjectManagerAdminRole(),
            ]],
            ['name' => 'delete_project', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getMarketingRole(),
                $this->getProjectManagerAdminRole(),
            ]],
            ['name' => 'export_project', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
                $this->getMarketingRole(),
            ]],
            ['name' => 'detail_project', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
                $this->getProjectManagerEntertainmentRole(),
                $this->getEntertainmentRole(),
                $this->getProductionRole(),
            ]],
            ['name' => 'assign_pic', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerAdminRole(),
            ]],
            ['name' => 'change_project_status', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getMarketingRole(),
                $this->getProjectManagerAdminRole(),
            ]],
            ['name' => 'assign_vj', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
                $this->getProjectManagerEntertainmentRole(),
            ]],

            ['name' => 'list_member', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
                $this->getProjectManagerEntertainmentRole(),
                $this->getEntertainmentRole(),
                $this->getProductionRole(),
            ]],
            ['name' => 'add_team_member', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
            ]],
            ['name' => 'list_team_transfer', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
                $this->getProjectManagerEntertainmentRole(),
            ]],
            ['name' => 'add_team_transfer', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
            ]],
            ['name' => 'add_entertainment_member', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
            ]],
            ['name' => 'list_entertainment_member', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
                $this->getProjectManagerEntertainmentRole(),
                $this->getEntertainmentRole(),
                $this->getProductionRole(),
            ]],
            ['name' => 'choose_team_to_request_transfer', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerEntertainmentRole(),
                $this->getProjectManagerAdminRole(),
            ]],

            ['name' => 'add_references', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
                $this->getProjectManagerEntertainmentRole(),
                $this->getEntertainmentRole(),
                $this->getProductionRole(),
            ]],
            ['name' => 'list_references', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
                $this->getProjectManagerEntertainmentRole(),
                $this->getEntertainmentRole(),
                $this->getProductionRole(),
            ]],
            ['name' => 'delete_references', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
                $this->getProjectManagerEntertainmentRole(),
                $this->getEntertainmentRole(),
                $this->getProductionRole(),
            ]],

            ['name' => 'add_shoreels', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
            ]],

            ['name' => 'cancel_equipment', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
            ]],
            ['name' => 'list_request_equipment', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
                $this->getProjectManagerEntertainmentRole(),
                $this->getEntertainmentRole(),
                $this->getItSupportRole(),
            ]],
            ['name' => 'request_inventory_event', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
            ]],

            ['name' => 'list_file_manager', 'group' => 'production', 'used' => [
                $this->getRootRole(),
                $this->getDirectorRole(),
                $this->getProjectManagerRole(),
                $this->getProjectManagerAdminRole(),
                $this->getProjectManagerEntertainmentRole(),
                $this->getEntertainmentRole(),
                $this->getProductionRole(),
                $this->getItSupportRole(),
            ]],
        ];
    }

    protected function getAllPermissions()
    {
        $permissions = [];
        $permissions = collect($permissions)->merge($this->dashboardPermission())
            ->merge($this->userManagementPermission())
            ->merge($this->employeePermission())
            ->merge($this->masterPermission())
            ->merge($this->inventoriesPermission())
            ->merge($this->settingPermission())
            ->merge($this->taskPermission())
            ->merge($this->projectPermission());

        return $permissions;
    }

    protected function seedPermissions()
    {
        $permissions = $this->getAllPermissions();

        foreach ($permissions as $permission) {
            $check = DB::table('permissions')
                ->where('name', $permission['name'])
                ->first();

            if (!$check) {
                Permission::create(collect($permission)->except('used')->toArray());
            }
        }
    }
}