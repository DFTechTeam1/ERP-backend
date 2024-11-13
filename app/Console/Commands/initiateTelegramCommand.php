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
//pass: jZneOS!p9:MUwB~8 user: u164909942.proderp ip: ftp://91.108.118.109

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
        $telegram->addCommand('my_project', 'Lihat project mu');
        $telegram->addCommand('my_task', 'Lihat tugas tugas mu');
        $telegram->addCommand('attendance', 'Absen jika kamu berada di luar kantor');
        $telegram->addCommand('create_task', 'Buat tugas untuk tim mu');
        $telegram->setMyCommand();

        $this->info('Telegram is ready to use');
    }
}
