<?php

namespace App\Console\Commands;

use App\Repository\RoleRepository;
use App\Repository\UserLoginHistoryRepository;
use App\Repository\UserRepository;
use App\Services\GeneralService;
use App\Services\RoleService;
use App\Services\UserService;
use Illuminate\Console\Command;
use Modules\Hrd\Repository\EmployeeRepository;

class GetTokenAsActor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:get-token-as-actor
                            {email : Email to be played}';

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
        $email = $this->argument('email');

        $service = new UserService(
            new UserRepository,
            new EmployeeRepository,
            new RoleRepository,
            new UserLoginHistoryRepository,
            new GeneralService,
            new RoleService
        );

        $token = $service->login(
            validated: [
                'email' => $email,
                'password' => ''
            ],
            onActing: true
        );

        $this->info(json_encode($token));
    }
}
