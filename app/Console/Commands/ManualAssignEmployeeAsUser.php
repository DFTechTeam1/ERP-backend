<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class ManualAssignEmployeeAsUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:assign-employee-to-user';

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
        $employees = \Modules\Hrd\Models\Employee::where('status', '!=', \App\Enums\Employee\Status::Inactive->value)
            ->get();

        \App\MOdels\User::where('email', '!=', 'admin@admin.com')
            ->delete();

        $directorPosition = json_decode(getSettingByKey('position_as_directors'), true);
        $directorPosition = collect($directorPosition)->map(function ($item) {
            return getIdFromUid($item, new \Modules\Company\Models\PositionBackup);
        })->toArray();

        $pmPosition = json_decode(getSettingByKey('position_as_project_manager'), true);
        $pmPosition = collect($pmPosition)->map(function ($item) {
            return getIdFromUid($item, new \Modules\Company\Models\PositionBackup);
        })->toArray();

        $marketingPosition = getSettingByKey('position_as_marketing');
        $marketingPosition = getIdFromUid($marketingPosition, new \Modules\Company\Models\PositionBackup);

        $projectManagerRole = Role::findByName('project manager');
        $productionRole = Role::findByName('production');
        $directorRole = Role::findByName('director');
        $marketingRole = Role::findByName('marketing');

        foreach ($employees as $employee) {
            $payload = [
                'employee_id' => $employee->id,
                'email' => $employee->email,
                'email_verified_at' => date('Y-m-d H:i:s'),
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
            ];

            if (in_array($employee->position_id, $directorPosition)) {
                $payload['is_director'] = true;
                $payload['role'] = 'director';
            } elseif (in_array($employee->position_id, $pmPosition)) {
                $payload['is_project_manager'] = true;
                $payload['role'] = 'project manager';
            } elseif ($employee->position_id == $marketingPosition) {
                $payload['is_employee'] = true;
                $payload['role'] = 'marketing';
            } else {
                $payload['is_employee'] = true;
                $payload['role'] = 'production';
            }

            $user = \App\Models\User::create(collect($payload)->except(['role'])->toArray());

            if ($payload['role'] == 'production') {
                $user->assignRole($productionRole);
            } elseif ($payload['role'] == 'project manager') {
                $user->assignRole($projectManagerRole);
            } elseif ($payload['role'] == 'director') {
                $user->assignRole($directorRole);
            } elseif ($payload['role'] == 'marketing') {
                $user->assignRole($marketingRole);
            }
        }
    }
}
