<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class ClearAllAuthenticatedSesssion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-all-authenticated-sesssion';

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
        $count = PersonalAccessToken::count();
        
        PersonalAccessToken::truncate();
        
        $this->info("Successfully logged out all users. Deleted {$count} tokens.");
        
        return 0;
    }
}
