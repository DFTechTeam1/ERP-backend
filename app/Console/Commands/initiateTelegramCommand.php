<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramService;
use Illuminate\Console\Command;

class initiateTelegramCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:initiate-telegram';

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
        $telegram = new TelegramService();
        $telegram->addCommand('connection', 'Hubungkan akun telegram kamu dengan sistem');
        $telegram->setMyCommand();

        $this->info('Telegram is ready to use');
    }
}
