<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

class ClearLogSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-log';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to clear the logs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // here we clear all logs from the past 3 days
        $dirs = scandir(storage_path('logs'));
        $allowed = [Carbon::now()->subDays(2)->format('Y-m-d'), Carbon::now()->subDay()->format('Y-m-d'), Carbon::now()->format('Y-m-d')];
        $allowed = collect($allowed)->map(function ($mapping) {
            return "laravel-{$mapping}.log";
        })->all();
        foreach ($dirs as $dir) {
            if ($dir != '..' && $dir != '.') {
                if (! in_array($dir, $allowed) && file_exists(storage_path("logs/{$dir}"))) {
                    unlink(storage_path("logs/{$dir}"));
                }
            }
        }
    }
}
