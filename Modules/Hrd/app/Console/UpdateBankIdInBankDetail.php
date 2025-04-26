<?php

namespace Modules\Hrd\Console;

use Illuminate\Console\Command;
use Modules\Company\Models\Bank;
use Modules\Hrd\Models\Employee;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class UpdateBankIdInBankDetail extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'hrd:update-bank-detail';

    /**
     * The console command description.
     */
    protected $description = 'This command used to update the bank_detail format in the database. We want to add bank_id on it.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $employees = Employee::selectRaw('id,bank_detail,nickname')
            ->whereNotNull('bank_detail')
            ->get();

        foreach ($employees as $employee) {
            $bankDetail = $employee->bank_detail;

            foreach ($bankDetail as $key => $bankData) {
                $currentBankName = $bankData['bank_name'] ?? 0;

                // get bank id from database
                $bank = Bank::selectRaw('bank_code')
                    ->where('name', $currentBankName)
                    ->first();

                if ($bank) { // update when bank is exists
                    $bankData['bank_id'] = $bank->bank_code;
                    $bankDetail[$key]['bank_id'] = $bank->bank_code;
                }

                unset($bankDetail[$key]['bank_name']);
            }
            // update employee data
            Employee::where('id', $employee->id)
                ->update(['bank_detail' => $bankDetail]);

            $this->info($employee->nickname . ' bank detail has been updated');
        }
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['example', InputArgument::REQUIRED, 'An example argument.'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null],
        ];
    }
}
