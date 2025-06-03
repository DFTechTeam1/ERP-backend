<?php

namespace App\Services\Telegram\Commands;

use Telegram\Bot\Commands\Command;

class StartCommand extends Command
{
    protected string $name = 'start';

    protected string $description = 'Start Command to get you started';

    public function handle()
    {
        $username = $this->getUpdate()->getMessage()->from->username;

        $this->replyWithMessage([
            'text' => "Halo {$username}, selamat datang di DFactory Data Center BOT!",
        ]);
        $this->replyWithMessage([
            'text' => 'Untuk mendaftarkan data diri, silahkan <b>copy</b> dan <b>paste</b> pesan text dibawah ini. Ganti employee_id dengan ID kamu',
            'parse_mode' => 'HTML',
        ]);
        $this->replyWithMessage([
            'text' => '/register employee_id',
        ]);
    }
}
