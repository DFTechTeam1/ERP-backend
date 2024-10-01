<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MassResetPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:mass-reset-password';

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
        $employees = \Modules\Hrd\Models\Employee::selectRaw('id,email')
            ->whereRaw("status != " . \App\Enums\Employee\Status::Inactive->value)
            ->get();

        $payloadExcel = [];
        foreach ($employees as $employee) {
            $user = \App\Models\User::where('employee_id', $employee->id)->first();
            if ($user) {
                $password = generateRandomPassword();
                $user->password = \Illuminate\Support\Facades\Hash::make($password);

                if ($user->save()) {
                    $payloadExcel[] = [
                        'email' => $user->email,
                        'password' => $password,
                    ];
                }
            }
        }

        return \Maatwebsite\Excel\Facades\Excel::store(new \App\Exports\MassResetPassword($payloadExcel), 'new_password.xlsx');   
    }
}
