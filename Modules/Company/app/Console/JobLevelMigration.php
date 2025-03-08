<?php

namespace Modules\Company\Console;

use Illuminate\Console\Command;
use Modules\Company\Models\JobLevel;
use Modules\Hrd\Models\Employee;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class JobLevelMigration extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'company:migrate-job-level';

    /**
     * The console command description.
     */
    protected $description = 'Assign new job level to current employee';

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
        $schemas = [
            '1' => 'C-Level',
            '2' => 'C-Level',
            '3' => 'Supervisor',
            '6' => 'Staff',
            '4' => 'Supervisor',
            '5' => 'Staff',
            '7' => 'Staff',
            '8' => 'Staff',
            '9' => 'Staff',
            '10' => 'Staff',
            '11' => 'Staff',
            '12' => 'Staff',
            '14' => 'Supervisor',
            '15' => 'Staff',
            '16' => 'Staff',
            '17' => 'Staff',
            '18' => 'Staff',
            '19' => 'Staff',
            '20' => 'Staff',
            '21' => 'Lead',
            '22' => 'Staff',
            '24' => 'Lead',
            '25' => 'Lead',
            '27' => 'Staff',
            '32' => 'Staff',
            '33' => 'Staff',
            '34' => 'Junior Staff',
            '35' => 'Staff',
            '37' => 'Staff',
            '38' => 'Staff',
            '39' => 'Junior Staff',
            '40' => 'Staff',
            '41' => 'Staff',
            '42' => 'Junior Staff',
            '43' => 'Staff',
            '46' => 'Staff',
            '47' => 'Staff',
            '48' => 'Staff',
            '49' => 'Staff',
            '52' => 'Staff',
            '53' => 'Staff',
            '54' => 'Staff',
            '55' => 'Staff',
            '58' => 'Staff',
            '59' => 'Staff',
            '60' => 'Staff',
            '61' => 'Staff',
            '62' => 'Junior Staff',
            '63' => 'Staff',
            '64' => 'Junior Staff',
            '65' => 'Staff',
            '66' => 'Staff',
            '67' => 'Staff',
            '68' => 'Staff'
        ];

        foreach ($schemas as $code => $level) {
            if (strlen($code) == 1) {
                $employeeId = 'DF00' . $code;
            } else if (strlen($code) == 2) {
                $employeeId = 'DF0' . $code;
            }

            $levelData = JobLevel::select('id')
                ->where('name', $level)
                ->first();

            Employee::where('employee_id', $employeeId)
                ->update([
                    'job_level_id' => $levelData->id
                ]);
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
