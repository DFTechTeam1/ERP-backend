<?php

namespace Modules\Nas\Console;

use Illuminate\Console\Command;
use Modules\Company\Models\Setting;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class SetNasConfiguration extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'nas:setup
        {--ip= : Define your current active IP}
        {--root= : Define folder root for the IP}';

    /**
     * The console command description.
     */
    protected $description = 'Setup nas configuration';

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
        $ip = $this->option('ip');
        $root = $this->option('root');

        if (empty($ip) && empty($root)) {
            $this->error('Please enter IP and root folder');
            return;
        }

        if (empty($ip)) {
            $this->error('Please enter IP');
            return;
        }

        if (empty($root)) {
            $this->error('Please enter root folder');
            return;
        }

        // store setting
        $currentIp = Setting::select('id')
            ->where('key', 'nas_current_ip')
            ->first();
        if ($currentIp) {
            $currentIp->value = $ip;
            $currentIp->save();
        } else {
            Setting::create([
                'key' => 'nas_current_ip',
                'value' => $ip,
            ]);
        }

        $currentRoot = Setting::select("id")
            ->where('key', 'nas_current_root')
            ->first();
        if ($currentRoot) {
            $currentRoot->value = $root;
            $currentRoot->save();
        } else {
            Setting::create([
                'key' => 'nas_current_root',
                'value' => $root,
            ]);
        }

        cachingSetting();

        $this->info("Success setting NAS configuration");
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
