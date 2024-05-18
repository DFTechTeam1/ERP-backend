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
            ['name' => 'Dashboard', 'parent_id' => null, 'icon' => '', 'group' => \App\Enums\Menu\Group::Dashboard->value, 'link' => '/admin/dashboard', 'permission' => 'dashboard_access'],
            ['name' => 'User Management', 'parent_id' => null, 'icon' => '', 'group' => \App\Enums\Menu\Group::Hrd->value, 'link' => '', 'permission' => ''],
            ['name' => 'Users', 'parent_id' => 2, 'icon' => '', 'group' => \App\Enums\Menu\Group::Hrd->value, 'link' => '/admin/user-management/users', 'permission' => 'list_user'],
            ['name' => 'Roles', 'parent_id' => 2, 'icon' => '', 'group' => \App\Enums\Menu\Group::Hrd->value, 'link' => '/admin/user-management/roles', 'permission' => 'list_role'],
            ['name' => 'Employees', 'parent_id' => null, 'icon' => '', 'group' => \App\Enums\Menu\Group::Hrd->value, 'link' => '/admin/employees/list', 'permission' => 'list_employee'],
            ['name' => 'Master', 'parent_id' => null, 'icon' => '', 'group' => \App\Enums\Menu\Group::Master->value, 'link' => '', 'permission' => ''],
            ['name' => 'Divisions', 'parent_id' => 6, 'icon' => '', 'group' => \App\Enums\Menu\Group::Master->value, 'link' => '/admin/master/divisions', 'permission' => 'list_division'],
            ['name' => 'Positions', 'parent_id' => 6, 'icon' => '', 'group' => \App\Enums\Menu\Group::Master->value, 'link' => '/admin/master/positions', 'permission' => 'list_position'],
            ['name' => 'Suppliers', 'parent_id' => null, 'icon' => '', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/suppliers', 'permission' => 'list_supplier'],
            ['name' => 'Brands', 'parent_id' => null, 'icon' => '', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/brands', 'permission' => 'list_brand'],
            ['name' => 'Units', 'parent_id' => null, 'icon' => '', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/units', 'permission' => 'list_unit'],
            ['name' => 'Inventory Types', 'parent_id' => null, 'icon' => '', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/inventory-types', 'permission' => 'list_inventory_type'],
            ['name' => 'Inventories', 'parent_id' => null, 'icon' => '', 'group' => \App\Enums\Menu\Group::Inventory->value, 'link' => '/admin/inventories/list', 'permission' => 'list_inventory'],
        ];

        foreach ($menus as $menu) {
            Menu::create($menu);
        }

        Schema::enableForeignKeyConstraints();
    }
}
