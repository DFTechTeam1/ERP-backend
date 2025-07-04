<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ResetProjectDealTransaction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-project-deal-transaction';

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
        \Illuminate\Support\Facades\DB::beginTransaction();

        try {
            \Illuminate\Support\Facades\DB::table('transaction_images')->delete();
            \Illuminate\Support\Facades\DB::table('transactions')->delete();
            \Illuminate\Support\Facades\DB::table('project_quotation_items')->delete();
            \Illuminate\Support\Facades\DB::table('project_quotations')->delete();
            \Illuminate\Support\Facades\DB::table('project_deal_marketings')->delete();
            \Illuminate\Support\Facades\DB::table('project_deals')->delete();

            \Illuminate\Support\Facades\DB::commit();
            $this->info('Success reset project deal transactions');
        } catch (\Throwable $th) {
            $this->error("Error: " . errorMessage($th));
            \Illuminate\Support\Facades\DB::rollBack();
        }
    }
}
