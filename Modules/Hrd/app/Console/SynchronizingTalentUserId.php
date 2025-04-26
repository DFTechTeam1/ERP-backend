<?php

namespace Modules\Hrd\Console;

use Illuminate\Console\Command;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Hrd\Services\TalentaService;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class SynchronizingTalentUserId extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'hrd:sync-talenta';

    /**
     * The console command description.
     */
    protected $description = 'Synchronize talent user id with current employee database';

    private $employeeRepo;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        $this->employeeRepo = new EmployeeRepository();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // here we limit only 10 per operation to avoid rate limiter on the API
        $databaseEmployees = $this->employeeRepo->list(
            select: 'id,employee_id,email,uid',
            where: "deleted_at IS NULL AND talenta_user_id IS NULL",
            limit: 1
        );

        $talentaService = new TalentaService();
        $talentaService->setUrl(type: 'all_employee');
        foreach ($databaseEmployees as $employee) {
            $talentaService->setUrlParams(params: ["email" => $employee->email]);

            $talentaUser = $talentaService->makeRequest();
            if (isset($talentaUser['data'])) {
                if (isset($talentaUser['data']['employees'])) {
                    if (count($talentaUser['data']['employees']) > 0) {
                        $this->employeeRepo->update([
                            'talenta_user_id' => $talentaUser['data']['employees'][0]['user_id']
                        ], $employee->uid);

                        $this->info('Success update ' . $employee->email . " data");
                    }
                }
            }
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
