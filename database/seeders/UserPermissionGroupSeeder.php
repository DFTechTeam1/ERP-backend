<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class UserPermissionGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $users = User::all();
        foreach ($users as $user) {
            $roles = $user->roles;

            foreach ($roles as $role) {
                $user->removeRole($role);

                $permissions = $role->permissions;

                foreach ($permissions as $permission) {
                    $role->revokePermissionTo($permission);
                }
            }
        }

        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
        ]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Schema::enableForeignKeyConstraints();
    }
}
