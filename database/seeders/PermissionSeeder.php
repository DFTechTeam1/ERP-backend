<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        DB::table('permissions')->truncate();

        $data = [
            ['name' => 'dashboard_access', 'group' => 'dashboard'],
            ['name' => 'create_user', 'group' => 'user_management'],
            ['name' => 'list_user', 'group' => 'user_management'],
            ['name' => 'edit_user', 'group' => 'user_management'],
            ['name' => 'detail_user', 'group' => 'user_management'],
            ['name' => 'delete_user', 'group' => 'user_management'],
            ['name' => 'create_role', 'group' => 'user_management'],
            ['name' => 'edit_role', 'group' => 'user_management'],
            ['name' => 'list_role', 'group' => 'user_management'],
            ['name' => 'delete_role', 'group' => 'user_management'],
            ['name' => 'create_employee', 'group' => 'employee'],
            ['name' => 'edit_employee', 'group' => 'employee'],
            ['name' => 'detail_employee', 'group' => 'employee'],
            ['name' => 'delete_employee', 'group' => 'employee'],
            ['name' => 'list_employee', 'group' => 'employee'],
            ['name' => 'invite_employee_as_user', 'group' => 'employee'],
            ['name' => 'import_employee', 'group' => 'employee'],
            ['name' => 'import_employee', 'group' => 'employee'],
            ['name' => 'list_division', 'group' => 'master'],
            ['name' => 'create_division', 'group' => 'master'],
            ['name' => 'edit_division', 'group' => 'master'],
            ['name' => 'delete_division', 'group' => 'master'],
            ['name' => 'list_position', 'group' => 'master'],
            ['name' => 'create_position', 'group' => 'master'],
            ['name' => 'edit_position', 'group' => 'master'],
            ['name' => 'delete_position', 'group' => 'master'],
            ['name' => 'list_supplier', 'group' => 'inventories'],
            ['name' => 'create_supplier', 'group' => 'inventories'],
            ['name' => 'edit_supplier', 'group' => 'inventories'],
            ['name' => 'delete_supplier', 'group' => 'inventories'],
            ['name' => 'list_brand', 'group' => 'inventories'],
            ['name' => 'create_brand', 'group' => 'inventories'],
            ['name' => 'edit_brand', 'group' => 'inventories'],
            ['name' => 'delete_brand', 'group' => 'inventories'],
            ['name' => 'list_unit', 'group' => 'inventories'],
            ['name' => 'create_unit', 'group' => 'inventories'],
            ['name' => 'edit_unit', 'group' => 'inventories'],
            ['name' => 'delete_unit', 'group' => 'inventories'],
            ['name' => 'list_inventory_type', 'group' => 'inventories'],
            ['name' => 'create_inventory_type', 'group' => 'inventories'],
            ['name' => 'edit_inventory_type', 'group' => 'inventories'],
            ['name' => 'delete_inventory_type', 'group' => 'inventories'],
            ['name' => 'list_inventory', 'group' => 'inventories'],
            ['name' => 'create_inventory', 'group' => 'inventories'],
            ['name' => 'edit_inventory', 'group' => 'inventories'],
            ['name' => 'delete_inventory', 'group' => 'inventories'],
            ['name' => 'import_inventory', 'group' => 'inventories'],
            ['name' => 'request_inventory', 'group' => 'inventories'],
            ['name' => 'request_inventory_event', 'group' => 'inventories'],
            ['name' => 'create_service_inventory', 'group' => 'inventories'],
            ['name' => 'reject_request_equipment', 'group' => 'inventories'],
            ['name' => 'accept_request_equipment', 'group' => 'inventories'],
            ['name' => 'list_addon', 'group' => 'addon'],
            ['name' => 'create_addon', 'group' => 'addon'],
            ['name' => 'update_addon', 'group' => 'addon'],
            ['name' => 'list_setting', 'group' => 'setting'],
            ['name' => 'setting_addon', 'group' => 'setting'],
            ['name' => 'setting_general', 'group' => 'setting'],
            ['name' => 'setting_kanban', 'group' => 'setting'],
            ['name' => 'list_project', 'group' => 'production'],
            ['name' => 'create_project', 'group' => 'production'],
            ['name' => 'edit_project', 'group' => 'production'],
            ['name' => 'delete_project', 'group' => 'production'],
            ['name' => 'add_team_member', 'group' => 'production'],
            ['name' => 'list_member', 'group' => 'production'],
            ['name' => 'move_task', 'group' => 'production'],
            ['name' => 'cancel_equipment', 'group' => 'production'],
            ['name' => 'cancel_equipment', 'group' => 'production'],
            ['name' => 'reject_request_equipment', 'group' => 'inventories'],
            ['name' => 'accept_request_equipment', 'group' => 'inventories'],
            ['name' => 'list_request_equipment', 'group' => 'inventories'],
            ['name' => 'move_task_to_progress', 'group' => 'production'],
            ['name' => 'move_task_to_review_pm', 'group' => 'production'],
            ['name' => 'move_task_to_review_client', 'group' => 'production'],
            ['name' => 'move_task_to_revise', 'group' => 'production'],
            ['name' => 'move_task_to_completed', 'group' => 'production'],
            ['name' => 'add_task', 'group' => 'production'],
            ['name' => 'list_task', 'group' => 'production'],
            ['name' => 'delete_task', 'group' => 'production'],
            ['name' => 'add_task_deadline', 'group' => 'production'],
            ['name' => 'add_task_attachment', 'group' => 'production'],
            ['name' => 'task_log_access', 'group' => 'production'],
            ['name' => 'proof_of_work_list', 'group' => 'production'],
            ['name' => 'change_project_status', 'group' => 'production'],
            ['name' => 'list_team_transfer', 'group' => 'production'],
        ];

        foreach ($data as $d) {
            DB::table('permissions')->where('name', $d['name'])->delete();

            $d['guard_name'] = 'sanctum';

            Permission::create($d);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Schema::enableForeignKeyConstraints();
    }
}
