<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class AppendPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
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
            ['name' => 'list_performance_report', 'group' => 'user_management'],
            ['name' => 'add_references', 'group' => 'production'],
            ['name' => 'add_shoreels', 'group' => 'production'],
            ['name' => 'assign_vj', 'group' => 'production'],
            ['name' => 'detail_permission', 'group' => 'production'],
            ['name' => 'detail_request_equipment', 'group' => 'inventories'],
            ['name' => 'list_custom_inventory', 'group' => 'inventories'],
            ['name' => 'create_custom_inventory', 'group' => 'inventories'],
            ['name' => 'edit_custom_inventory', 'group' => 'inventories'],
            ['name' => 'detail_project', 'group' => 'production'],
            ['name' => 'list_file_manager', 'group' => 'production'],
        ];

        foreach ($permissions as $permission) {
            $check = \Illuminate\Support\Facades\DB::table('permissions')
                ->select("id")
                ->where('name', $permission['name'])
                ->first();

            if (!$check) {
                Permission::create($permission);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
