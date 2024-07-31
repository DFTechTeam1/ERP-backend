<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Schema::disableForeignKeyConstraints();

        DB::table('roles')->truncate();

        $roles = [
            [
                'name' => 'root',
            ],
            [
                'name' => 'marketing',
            ],
            [
                'name' => 'project manager',
            ],
            [
                'name' => 'production',
            ],
            [
                'name' => 'it support',
            ],
            [
                'name' => 'director',
            ],
        ];

        foreach ($roles as $role) {
            try {
                DB::table('roles')->where('name', $role['name'])->delete();
                
            } catch (\Throwable $th) {}

            $roleData = Role::create(['name' => $role['name'], 'guard_name' => 'sanctum']);

            if ($role['name'] == 'root' || $role['name'] == 'director') {
                $permissions = Permission::all();
                foreach ($permissions as $permission) {
                    $roleData->givePermissionTo($permission);
                }
            }

        }

        Schema::enableForeignKeyConstraints();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
