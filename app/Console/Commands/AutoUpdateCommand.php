<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AutoUpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:auto-update-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Running all necessary commands after the update';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Artisan::call('optimize:clear');
        Artisan::call('app:update-uid-to-existing-invoice');
    }
}
