<?php

namespace Modules\Finance\Console;

use Illuminate\Console\Command;
use Modules\Finance\Models\Invoice;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MigrateSourceableInTransaction extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:migrate-sourceable-transaction';

    /**
     * The console command description.
     */
    protected $description = 'Adding sourcable data in existing transactions';

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
        // message start processing
        $this->info('Starting sourceable data migration in transactions...');

        $transactions = \Modules\Finance\Models\Transaction::where('sourceable_type', '')
            ->get();
        
        // progress
        $this->output->progressStart($transactions->count());
        foreach ($transactions as $trx) {
            $relatedModel = get_class(new Invoice());

            \Modules\Finance\Models\Transaction::where('id', $trx->id)
                ->update([
                    'sourceable_type' => $relatedModel,
                    'sourceable_id' => $trx->invoice_id,
                ]);

            $this->output->progressAdvance();
        }
        $this->output->progressFinish();

        // message
        $this->info('Sourceable data migration completed successfully.');
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
