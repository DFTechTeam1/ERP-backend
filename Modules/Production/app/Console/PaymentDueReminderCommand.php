<?php

namespace Modules\Production\Console;

use App\Services\GeneralService;
use Illuminate\Console\Command;
use Modules\Production\Jobs\PaymentDueReminderJob;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class PaymentDueReminderCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:get-and-send-reminder-payment-due';

    /**
     * The console command description.
     */
    protected $description = 'Sending notification to marketing about payment due projects.';

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
        $generalService = new GeneralService();

        $data = $generalService->getUpcomingPaymentDue();

        if ($data->count() > 0) {
            PaymentDueReminderJob::dispatch($data);
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
