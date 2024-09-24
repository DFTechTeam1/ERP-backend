<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ChangeEmployeeOfficalEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:shifting-email';

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
        $employees = \Modules\Hrd\Models\Employee::selectRaw('name,id,employee_id,email')
            ->whereRaw("status != " . \App\Enums\Employee\Status::Inactive->value)
            ->get();

        $payloadEmployee = [];
        $payloadUser = [];
        foreach ($employees as $employee) {

            $exp = explode(' ', $employee->name);
            $firstname = $exp[0];
            $lastname = array_pop($exp);
            $fullname = $firstname . $lastname;
            $emailFormat = $fullname . '@dfactory.pro';
            $password = generateRandomSymbol() . generateRandomPassword() . generateRandomSymbol();

            if ($employee->employee_id == 'DF001') {
                $emailFormat = 'wesley@dfactory.pro';
            } else if ($employee->employee_id == 'DF002') {
                $emailFormat = 'edwin@dfactory.pro';                
            } else if ($employee->employee_id == 'DF025') {
                $emailFormat = 'galih@dfactory.pro';
            } else if ($employee->employee_id == 'DF046') {
                $emailFormat = 'dhea@dfactory.pro';
            } else if ($employee->employee_id == 'DF049') {
                $emailFormat = 'gumilang@dfactory.pro';
            }

            $payloadEmployee[] = ['email' => strtolower($emailFormat), 'condition' => "employee_id = '" . $employee->id . "'"];
        }

        foreach ($payloadEmployee as $payload) {
            \App\Models\User::whereRaw($payload['condition'])
                ->update(['email' => $payload['email']]);
        }

        $this->info('success: ' . json_encode($payloadEmployee));
    }
}
