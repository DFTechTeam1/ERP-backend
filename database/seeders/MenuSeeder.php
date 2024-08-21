<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        \App\Models\Menu::truncate();

        $menus = [
            ['name' => 'dashboard', 'parent_id' => null, 'icon' => 'menus/dashboard.png', 'group' => \App\Enums\Menu\Group::Dashboard->value, 'link' => '/admin/dashboard', 'permission' => 'dashboard_access', 'lang_id' => 'Dashboard', 'lang_en' => 'Dashboard'],
            ['name' => 'userManagement', 'parent_id' => null, 'icon' => 'menus/users.png', 'group' => \App\Enums\Menu\Group::Hrd->value, 'link' => '', 'permission' => '', 'lang_en' => 'User Management', 'lang_id' => 'Manajement Pengguna'],
            ['name' => 'users', 'parent_id' => 2, 'icon' => '', 'group' => \App\Enums\Menu\Group::Hrd->value, 'link' => '/admin/user-management/users', 'permission' => 'list_user', 'lang_id' => 'Pengguna', 'lang_en' => 'Users'],
            ['name' => 'roles', 'parent_id' => 2, 'icon' => '', 'group' => \App\Enums\Menu\Group::Hrd->value, 'link' => '/admin/user-management/roles', 'permission' => 'list_role', 'lang_id' => 'Roles', 'lang_en' => 'Roles'],
            ['name' => 'employees', 'parent_id' => null, 'icon' => 'menus/employee.png', 'group' => \App\Enums\Menu\Group::Hrd->value, 'link' => '/admin/employees/list', 'permission' => 'list_employee', 'lang_id' => 'Karyawan', 'lang_en' => 'Employees'],
            ['name' => 'master', 'parent_id' => null, 'icon' => 'menus/master.png', 'group' => \App\Enums\Menu\Group::Master->value, 'link' => '', 'permission' => '', 'lang_id' => 'Master', 'lang_en' => 'Master'],
            ['name' => 'divisions', 'parent_id' => 6, 'icon' => '', 'group' => \App\Enums\Menu\Group::Master->value, 'link' => '/admin/master/divisions', 'permission' => 'list_division', 'lang_id' => 'Divisi', 'lang_en' => 'Divisions'],
            ['name' => 'projectClass', 'parent_id' => 6, 'icon' => '', 'group' => \App\Enums\Menu\Group::Master->value, 'link' => '/admin/master/projectClass', 'permission' => 'list_division', 'lang_id' => 'Event Class', 'lang_en' => 'Project Class'],
            ['name' => 'positions', 'parent_id' => 6, 'icon' => '', 'group' => \App\Enums\Menu\Group::Master->value, 'link' => '/admin/master/positions', 'permission' => 'list_position', 'lang_id' => 'Posisi', 'lang_en' => 'Positions'],
            ['name' => 'suppliers', 'parent_id' => null, 'icon' => 'menus/supplier.png', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/suppliers', 'permission' => 'list_supplier', 'lang_id' => 'Pemasok', 'lang_en' => 'Suppliers'],
            ['name' => 'brands', 'parent_id' => null, 'icon' => 'menus/brand.png', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/brands', 'permission' => 'list_brand', 'lang_id' => 'Merek', 'lang_en' => 'Brands'],
            ['name' => 'units', 'parent_id' => null, 'icon' => 'menus/unit.png', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/units', 'permission' => 'list_unit', 'lang_id' => 'Units', 'lang_en' => 'Units'],
            ['name' => 'inventoryTypes', 'parent_id' => null, 'icon' => 'menus/inventory_type.png', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/inventory-types', 'permission' => 'list_inventory_type', 'lang_id' => 'Tipe Inventaris', 'lang_en' => 'Inventory Types'],
            ['name' => 'inventories', 'parent_id' => null, 'icon' => 'menus/inventory.png', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/list', 'permission' => 'list_inventory', 'lang_id' => 'Inventaris', 'lang_en' => 'Inventories'],
            ['name' => 'customInventories', 'parent_id' => null, 'icon' => 'menus/custom-inventory.png', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/custom', 'permission' => 'list_inventory', 'lang_id' => 'Custom Inventaris', 'lang_en' => 'Custom Inventories'],
            ['name' => 'requestEquipment', 'parent_id' => null, 'icon' => 'menus/inventory.png', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/request-equipment', 'permission' => 'list_request_equipment', 'lang_id' => 'Permintaan Equipment', 'lang_en' => 'Request Equipment'],
            // ['name' => 'Addon List', 'parent_id' => null, 'icon' => 'menus/addon.png', 'group' => \App\Enums\Menu\Group::Addon->value, 'link' => '/panel/addons', 'permission' => 'list_addon'],
            ['name' => 'setting', 'parent_id' => null, 'icon' => 'menus/setting.png', 'group' => \App\Enums\Menu\Group::Dashboard->value, 'link' => '/admin/setting', 'permission' => 'list_setting', 'lang_id' => 'Pengaturan', 'lang_en' => 'Setting'],
            ['name' => 'projects', 'parent_id' => null, 'icon' => 'menus/projects.png', 'group' => \App\Enums\Menu\Group::Production->value, 'link' => '/admin/production/projects', 'permission' => 'list_project', 'lang_id' => 'Event', 'lang_en' => 'Projects'],
            ['name' => 'tasks', 'parent_id' => null, 'icon' => 'menus/task.png', 'group' => \App\Enums\Menu\Group::Production->value, 'link' => '/admin/production/tasks', 'permission' => 'list_task', 'lang_id' => 'Tugas', 'lang_en' => 'Tasks'],
            ['name' => 'teamTransfer', 'parent_id' => null, 'icon' => 'menus/transfer.png', 'group' => \App\Enums\Menu\Group::Production->value, 'link' => '/admin/production/team-transfer', 'permission' => 'list_team_transfer', 'lang_id' => 'Transfer Anggota', 'lang_en' => 'Team Transfer'],
            ['name' => 'performanceReport', 'parent_id' => null, 'icon' => 'menus/performance-report.png', 'group' => \App\Enums\Menu\Group::Hrd->value, 'link' => '/admin/employees/performanceReport', 'permission' => 'list_performance_report', 'lang_id' => 'Laporan Kinerja', 'lang_en' => 'Performance Report'],
        ];

        foreach ($menus as $menu) {
            Menu::create($menu);
        }

        Schema::enableForeignKeyConstraints();
    }
}
