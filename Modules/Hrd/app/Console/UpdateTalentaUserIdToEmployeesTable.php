<?php

namespace Modules\Hrd\Console;

use Illuminate\Console\Command;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Services\TalentaService;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class UpdateTalentaUserIdToEmployeesTable extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'hrd:sync-talenta-user-id';

    /**
     * The console command description.
     */
    protected $description = 'Command to update talenta user id. Fetch user id from Talenta then update in local database';

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
        $talenta = new TalentaService();
        $talenta->setUrl(type: 'all_employees', env: 'prod');
        $talenta->setUrlParams(params: [
            'status' => 0,
            'limit' => 50
        ]);

        $response = $talenta->makeRequest();

        if (
            ($response) &&
            (
                (isset($response['data'])) &&
                (isset($response['data']['employees']))
            )
        ) {
            foreach ($response['data']['employees'] as $employee) {
                $userId = $employee['user_id'];
                $employeeId = $employee['employment']['employee_id'];

                $currentEmployee = Employee::selectRaw('id,name,nickname,talenta_user_id')
                    ->where("employee_id", $employeeId)
                    ->first();

                if ($currentEmployee) {
                    $currentEmployee->talenta_user_id = $userId;

                    $currentEmployee->save();

                    $this->info("Successfully change the talenta_user_id of {$currentEmployee->nickname}");
                }
            }
        } else {
            $this->error("Failed connected with Talenta Server");
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
