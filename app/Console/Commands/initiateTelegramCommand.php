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
        $commands = [
            'local' => [
                [
                    'name' => 'connection',
                    'description' => 'Hubungkan akun telegram kamu dengan sistem'
                ],
                [
                    'name' => 'my_project',
                    'description' => 'Lihat project mu'
                ],
                [
                    'name' => 'my_task',
                    'description' => 'Lihat tugas tugas mu'
                ],
                [
                    'name' => 'attendance',
                    'description' => 'Absen jika kamu berada di luar kantor'
                ],
                [
                    'name' => 'create_task',
                    'description' => 'Buat tugas untuk tim mu'
                ],
                [
                    'name' => 'manage_nas',
                    'description' => 'Kelola NAS disini'
                ]
            ],
            'production' => [
                [
                    'name' => 'connection',
                    'description' => 'Hubungkan akun telegram kamu dengan sistem'
                ],
                [
                    'name' => 'my_project',
                    'description' => 'Lihat project mu'
                ],
            ]
        ];

        $telegram = new TelegramService();

        $selected = $commands[env('APP_ENV')];
        foreach ($selected as $command) {
            $telegram->addCommand($command['name'], $command['description']);
        }

        $telegram->setMyCommand();

        $this->info('Telegram is ready to use');
    }
}
