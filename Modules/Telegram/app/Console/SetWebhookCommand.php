<?php

namespace Modules\Telegram\Console;

use App\Services\Telegram\TelegramService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class SetWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'telegram:set-webhook';

    /**
     * The console command description.
     */
    protected $description = 'Set webhook for telegram.';

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
        $service = new TelegramService;
        $set = $service->setWebhook(env('TELEGRAM_WEBHOOK_URL'));
        logging('set webhook', $set);
        if ($set['ok']) {
            $this->info('Webhook is already pointing to '.env('TELEGRAM_WEBHOOK_URL'));
        }
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
