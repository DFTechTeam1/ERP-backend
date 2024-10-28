<?php

namespace App\Services\Telegram\Commands;

use Telegram\Bot\Commands\Command;

class StartCommand extends Command {
    protected string $name = 'start';
    protected string $description = 'Start Command to get you started';

    public function handle()
    {
        $username = $this->getUpdate()->getMessage()->from->username;

        $this->replyWithMessage([
            'text' => "Hey {$username}, there! Welcome to our bot!",
        ]);
    }
}
