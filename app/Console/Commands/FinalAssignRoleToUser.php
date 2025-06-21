<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class FinalAssignRoleToUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-user-role-permission';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $users = \App\Models\User::with('employee.position')->get();

        foreach ($users as $user) {
            $user->syncRoles([]);
        }

        foreach ($users as $user) {
            if ($user->employee) {
                if ($user->employee->position->name == 'Lead Project Manager' || $user->employee->position->name == 'Head of Creative' || $user->employee->position->name == 'Full Stack Developer') {
                    $role = Role::findByName('director');
                } elseif (
                    $user->employee->position->name == 'Project Manager'
                ) {
                    $role = Role::findByName('project manager');
                } elseif (
                    $user->employee->position->name == 'Animator' ||
                    $user->employee->position->name == 'Compositor' ||
                    $user->employee->position->name == '3D Modeller' ||
                    $user->employee->position->name == '3D Generalist' ||
                    $user->employee->position->name == 'Assistant Project Manager' ||
                    $user->employee->position->name == '3D Animator'
                ) {
                    $role = Role::findByName('production');
                } elseif (
                    $user->employee->position->name == 'Operator' ||
                    $user->employee->position->name == 'Visual Jockey'
                ) {
                    $role = Role::findByName('entertainment');
                } elseif (
                    $user->employee->position->name == 'Lead Marcomm' ||
                    $user->employee->position->name == 'Marketing Staff' ||
                    $user->employee->position->name == 'Marcomm Staff'
                ) {
                    $role = Role::findByName('marketing');
                } elseif (
                    $user->employee->position->name == 'HR & TA Admin' ||
                    $user->employee->position->name == 'HR Generalist'
                ) {
                    $role = Role::findByName('hrd');
                } elseif (
                    $user->employee->position->name == 'IT Technical Support'
                ) {
                    $role = Role::findByName('it support');
                } elseif (
                    $user->employee->position->name == 'Admin Staff'
                ) {
                    $role = Role::findByName('finance');
                } else {
                    $role = null;
                }

                if ($role) {
                    $user->assignRole($role);
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
