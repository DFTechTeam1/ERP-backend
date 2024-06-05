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
