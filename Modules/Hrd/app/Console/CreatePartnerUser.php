<?php

namespace Modules\Hrd\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CreatePartnerUser extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:create-partner-user';

    /**
     * The console command description.
     */
    protected $description = 'Create a new partner user. Each partner apps will have a user to integrate with ERP system. This command is used to create that user.';

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
        $this->info('Creating partner user...');

        User::updateOrCreate(
            ['email' => config('app.partner_email')],
            [
                'name' => 'Partner User',
                'password' => Hash::make(config('app.partner_password')),
                'email_verified_at' => now(),
                'user_status' => true,
                'is_employee' => false,
                'is_director' => false,
                'is_project_manager' => false,
            ]
        );

        $this->info('Partner user created successfully.');
        return Command::SUCCESS;
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
