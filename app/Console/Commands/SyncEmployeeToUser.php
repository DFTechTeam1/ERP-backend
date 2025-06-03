<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncEmployeeToUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:employee-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncronize data between employee and user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $employees = \Modules\Hrd\Models\Employee::selectRaw('id,user_id,email')->get();

        foreach ($employees as $employee) {
            $userData = \App\Models\User::where('email', $employee->email)
                ->where('employee_id', null)
                ->first();
            if (
                ($userData) &&
                (! $userData->employee_id)
            ) {
                \App\Models\User::where('email', $employee->email)
                    ->update(['employee_id' => $employee->id]);
            }
        }

        $users = \App\Models\User::selectRaw('id,email')->get();

        foreach ($users as $user) {
            $employeeData = \Modules\Hrd\Models\Employee::select('user_id')->where('email', $user->email)->first();
            if (
                ($employeeData) &&
                (! $employeeData->user_id)
            ) {
                \Modules\Hrd\Models\Employee::where('email', $user->email)
                    ->update(['user_id' => $user->id]);
            }
        }

        $this->info('All is Sync');
    }
}
