<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AppendRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = [
            [
                'name' => 'entertainment',
            ],
            [
                'name' => 'pic entertainment',
            ],
        ];

        foreach ($roles as $role) {
            $check = \Illuminate\Support\Facades\DB::table('roles')
                ->select('id')
                ->where('name', $role['name'])
                ->first();

            if (! $check) {
                $roleData = Role::create(['name' => $role['name'], 'guard_name' => 'sanctum']);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
