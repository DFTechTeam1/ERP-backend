<?php

namespace Database\Seeders;

use App\Models\Menu;
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
            ['app_type' => 'old', 'name' => 'dashboard', 'parent_id' => null, 'icon' => 'menus/dashboard.png', 'group' => \App\Enums\Menu\Group::Dashboard->value, 'link' => '/admin/dashboard', 'permission' => 'dashboard_access', 'lang_id' => 'Dashboard', 'lang_en' => 'Dashboard'],
            ['app_type' => 'office', 'new_link' => '/dashboard/hr', 'new_icon' => 'pi pi-chart-line', 'name' => 'dashboardStudent', 'parent_id' => null, 'icon' => 'menus/dashboard.png', 'group' => \App\Enums\Menu\Group::Dashboard->value, 'link' => '/admin/dashboard/hr', 'permission' => 'dashboard_hrd', 'lang_id' => 'Dashboard', 'lang_en' => 'Dashboard'],
            ['app_type' => 'office', 'new_link' => '/logs', 'new_icon' => 'pi pi-microchip', 'name' => 'logs', 'parent_id' => null, 'icon' => 'menus/employee.png', 'group' => \App\Enums\Menu\Group::Dashboard->value, 'link' => '/admin/employees/list', 'permission' => 'list_logs', 'lang_id' => 'Logs', 'lang_en' => 'Logs'],
            ['app_type' => 'old', 'name' => 'userManagement', 'parent_id' => null, 'icon' => 'menus/users.png', 'group' => \App\Enums\Menu\Group::Hrd->value, 'link' => '', 'permission' => '', 'lang_en' => 'User Management', 'lang_id' => 'Manajement Pengguna'],
            // ['app_type' => 'old', 'name' => 'users', 'parent_id' => 2, 'icon' => '', 'group' => \App\Enums\Menu\Group::Hrd->value, 'link' => '/admin/user-management/users', 'permission' => 'list_user', 'lang_id' => 'Pengguna', 'lang_en' => 'Users'],
            // ['app_type' => 'old', 'name' => 'roles', 'parent_id' => 2, 'icon' => '', 'group' => \App\Enums\Menu\Group::Hrd->value, 'link' => '/admin/user-management/roles', 'permission' => 'list_role', 'lang_id' => 'Roles', 'lang_en' => 'Roles'],
            // ['app_type' => 'old', 'name' => 'employees', 'parent_id' => null, 'icon' => 'menus/employee.png', 'group' => \App\Enums\Menu\Group::Hrd->value, 'link' => '/admin/employees/list', 'permission' => 'list_employee', 'lang_id' => 'Karyawan', 'lang_en' => 'Employees'],
            ['app_type' => 'old', 'name' => 'master', 'parent_id' => null, 'icon' => 'menus/master.png', 'group' => \App\Enums\Menu\Group::Master->value, 'link' => '', 'permission' => '', 'lang_id' => 'Master', 'lang_en' => 'Master'],
            ['app_type' => 'old', 'name' => 'projectClass', 'parent_id' => null, 'icon' => '', 'group' => \App\Enums\Menu\Group::Master->value, 'link' => '/admin/master/projectClass', 'permission' => 'list_division', 'lang_id' => 'Event Class', 'lang_en' => 'Project Class'],
            // ['app_type' => 'old', 'name' => 'positions', 'parent_id' => 6, 'icon' => '', 'group' => \App\Enums\Menu\Group::Master->value, 'link' => '/admin/master/positions', 'permission' => 'list_position', 'lang_id' => 'Posisi', 'lang_en' => 'Positions'],

            ['app_type' => 'office', 'new_link' => '/dashboard', 'new_icon' => 'pi pi-chart-scatter', 'name' => 'dashboard', 'parent_id' => null, 'icon' => 'menus/dashboard.png', 'group' => \App\Enums\Menu\Group::Dashboard->value, 'link' => '/admin/dashboard', 'permission' => 'dashboard_access', 'lang_id' => 'Dashboard', 'lang_en' => 'Dashboard'],
            ['app_type' => 'office', 'new_link' => '/employees', 'new_icon' => 'pi pi-users', 'name' => 'employees', 'parent_id' => null, 'icon' => 'menus/employee.png', 'group' => \App\Enums\Menu\Group::Hrd->value, 'link' => '/admin/employees/list', 'permission' => 'list_employee', 'lang_id' => 'Karyawan', 'lang_en' => 'Employees'],
            ['app_type' => 'office', 'new_link' => '/users', 'new_icon' => 'pi pi-user', 'name' => 'users', 'parent_id' => null, 'icon' => 'menus/master.png', 'group' => \App\Enums\Menu\Group::Hrd->value, 'link' => '/admin/user-management/users', 'permission' => 'list_user', 'lang_id' => 'Pengguna', 'lang_en' => 'Users'],
            ['app_type' => 'office', 'new_link' => '/roles', 'new_icon' => 'pi pi-cloud', 'name' => 'roles', 'parent_id' => null, 'icon' => 'menus/master.png', 'group' => \App\Enums\Menu\Group::Hrd->value, 'link' => '/admin/user-management/roles', 'permission' => 'list_role', 'lang_id' => 'Roles', 'lang_en' => 'Roles'],
            ['app_type' => 'office', 'new_link' => '/branches', 'new_icon' => 'pi pi-sitemap', 'name' => 'branches', 'parent_id' => null, 'icon' => 'menus/master.png', 'group' => \App\Enums\Menu\Group::Master->value, 'link' => '/admin/master/branches', 'permission' => 'list_branch', 'lang_id' => 'Cabang Perusahaan', 'lang_en' => 'Branches'],
            ['app_type' => 'office', 'new_link' => '/divisions', 'new_icon' => 'pi pi-align-justify', 'name' => 'divisions', 'parent_id' => null, 'icon' => 'menus/master.png', 'group' => \App\Enums\Menu\Group::Master->value, 'link' => '/admin/master/divisions', 'permission' => 'list_division', 'lang_id' => 'Divisi', 'lang_en' => 'Divisions'],
            ['app_type' => 'office', 'new_link' => '/positions', 'new_icon' => 'pi pi-align-justify', 'name' => 'positions', 'parent_id' => null, 'icon' => 'menus/master.png', 'group' => \App\Enums\Menu\Group::Master->value, 'link' => '/admin/master/positions', 'permission' => 'list_position', 'lang_id' => 'Posisi', 'lang_en' => 'Positions'],
            ['app_type' => 'office', 'new_link' => '/suppliers', 'new_icon' => 'pi pi-truck', 'name' => 'suppliers', 'parent_id' => null, 'icon' => 'menus/supplier.png', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/suppliers', 'permission' => 'list_supplier', 'lang_id' => 'Pemasok', 'lang_en' => 'Suppliers'],
            ['app_type' => 'office', 'new_link' => '/brands', 'new_icon' => 'pi pi-file', 'name' => 'brands', 'parent_id' => null, 'icon' => 'menus/brand.png', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/brands', 'permission' => 'list_brand', 'lang_id' => 'Merek', 'lang_en' => 'Brands'],
            ['app_type' => 'office', 'new_link' => '/units', 'new_icon' => 'pi pi-briefcase', 'name' => 'units', 'parent_id' => null, 'icon' => 'menus/unit.png', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/units', 'permission' => 'list_unit', 'lang_id' => 'Units', 'lang_en' => 'Units'],
            ['app_type' => 'office', 'new_link' => '/inventory-types', 'new_icon' => 'pi pi-briefcase', 'name' => 'inventoryTypes', 'parent_id' => null, 'icon' => 'menus/inventory_type.png', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/inventory-types', 'permission' => 'list_inventory_type', 'lang_id' => 'Tipe Inventaris', 'lang_en' => 'Inventory Types'],
            ['app_type' => 'office', 'new_link' => '/inventories', 'new_icon' => 'pi pi-box', 'name' => 'inventories', 'parent_id' => null, 'icon' => 'menus/inventory.png', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/list', 'permission' => 'list_inventory', 'lang_id' => 'Inventaris', 'lang_en' => 'Inventories'],
            ['app_type' => 'office', 'new_link' => '/custom-inventories', 'new_icon' => 'pi pi-gift', 'name' => 'customInventories', 'parent_id' => null, 'icon' => 'menus/custom-inventory.png', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/custom', 'permission' => 'list_inventory', 'lang_id' => 'Custom Inventaris', 'lang_en' => 'Custom Inventories'],
            ['app_type' => 'office', 'new_link' => '/request-inventories', 'new_icon' => 'pi pi-inbox', 'name' => 'requestInventories', 'parent_id' => null, 'icon' => 'menus/custom-inventory.png', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/request-inventories', 'permission' => 'list_inventory', 'lang_id' => 'Permintaan Barang', 'lang_en' => 'Request Inventories'],

            ['app_type' => 'old', 'name' => 'requestEquipment', 'parent_id' => null, 'icon' => 'menus/inventory.png', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/request-equipment', 'permission' => 'list_request_equipment', 'lang_id' => 'Permintaan Equipment', 'lang_en' => 'Request Equipment'],
            // ['app_type' => 'old, 'name' => 'Addon List', 'parent_id' => null, 'icon' => 'menus/addon.png', 'group' => \App\Enums\Menu\Group::Addon->value, 'link' => '/panel/addons', 'permission' => 'list_addon'],
            ['app_type' => 'old', 'name' => 'setting', 'parent_id' => null, 'icon' => 'menus/setting.png', 'group' => \App\Enums\Menu\Group::Dashboard->value, 'link' => '/admin/setting', 'permission' => 'list_setting', 'lang_id' => 'Pengaturan', 'lang_en' => 'Setting'],
            ['app_type' => 'old', 'name' => 'projects', 'parent_id' => null, 'icon' => 'menus/projects.png', 'group' => \App\Enums\Menu\Group::Production->value, 'link' => '/admin/production/projects', 'permission' => 'list_project', 'lang_id' => 'Event', 'lang_en' => 'Projects'],
            ['app_type' => 'old', 'name' => 'projectsDeals', 'parent_id' => null, 'icon' => 'menus/deals.png', 'group' => \App\Enums\Menu\Group::Production->value, 'link' => '/admin/deals', 'permission' => 'list_deals', 'lang_id' => 'Deals', 'lang_en' => 'Deals'],
            ['app_type' => 'old', 'name' => 'fileManager', 'parent_id' => null, 'icon' => 'menus/folder.png', 'group' => \App\Enums\Menu\Group::Production->value, 'link' => '/admin/production/files', 'permission' => 'list_file_manager', 'lang_id' => 'File Manager', 'lang_en' => 'File Manager'],
            ['app_type' => 'old', 'name' => 'tasks', 'parent_id' => null, 'icon' => 'menus/task.png', 'group' => \App\Enums\Menu\Group::Production->value, 'link' => '/admin/production/tasks', 'permission' => 'list_task', 'lang_id' => 'Tugas', 'lang_en' => 'Tasks'],
            ['app_type' => 'old', 'name' => 'teamTransfer', 'parent_id' => null, 'icon' => 'menus/transfer.png', 'group' => \App\Enums\Menu\Group::Production->value, 'link' => '/admin/production/team-transfer', 'permission' => 'list_team_transfer', 'lang_id' => 'Transfer Anggota', 'lang_en' => 'Team Transfer'],
            ['app_type' => 'old', 'name' => 'performanceReport', 'parent_id' => null, 'icon' => 'menus/performance-report.png', 'group' => \App\Enums\Menu\Group::Hrd->value, 'link' => '/admin/employees/performanceReport', 'permission' => 'list_performance_report', 'lang_id' => 'Laporan Kinerja', 'lang_en' => 'Performance Report'],
        ];

        foreach ($menus as $menu) {
            Menu::create($menu);
        }

        Schema::enableForeignKeyConstraints();
    }
}
