<?php

namespace Modules\Hrd\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Hrd\Models\EmployeeTimeoff;
use Modules\Hrd\Services\TalentaService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class AutoUpdateEmployeeTimeoff extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'hrd:update-timeoff-talenta';

    /**
     * The console command description.
     */
    protected $description = 'This used to fetch all data about who is off today from Talenta. This command will be run every midnight everyday';

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
        $talenta = new TalentaService;

        $talenta->setUrl(type: 'timeoff_list');
        $talenta->setUrlParams(params: [
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->endOfMonth()->toDateString(),
            'status' => 'approved',
        ]);

        $response = $talenta->makeRequest();

        if (
            ($response) &&
            (
                (isset($response['data'])) &&
                (isset($response['data']['time_off']))
            )
        ) {
            foreach ($response['data']['time_off'] as $timeOff) {
                $payload = [
                    'time_off_id' => $timeOff['id'],
                    'talenta_user_id' => $timeOff['user_id'],
                    'policy_name' => $timeOff['policy_name'],
                    'request_type' => $timeOff['request_type'],
                    'file_url' => $timeOff['file_url'] ?? null,
                    'start_date' => $timeOff['start_date'],
                    'end_date' => $timeOff['end_date'],
                    'status' => $timeOff['status'],
                ];

                EmployeeTimeoff::create($payload);
            }

            $this->info('Successfully update '.count($response['data']['time_off']).' timeoff data');
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
