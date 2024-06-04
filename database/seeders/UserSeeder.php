<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        User::truncate();
        Schema::enableForeignKeyConstraints();

        $root = Role::findByName('root');
        
        $users = [
            [
                'email' => 'admin@admin.com',
                'password' => Hash::make('password'),
                'email_verified_at' => date('Y-m-d H:i:s'),
                'uid' => Uuid::uuid4(),
            ]
        ];

        foreach ($users as $user) {
            $userData = User::create($user);

            $userData->assignRole($root);
        }
    }
}
