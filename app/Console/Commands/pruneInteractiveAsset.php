<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class pruneInteractiveAsset extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prune-interactive-asset';

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
        $date = date('Y-m-d', strtotime('-1 day'));
        $deviceIds = [1,2,3];

        echo $date;

        foreach ($deviceIds as $deviceId) {
            $path = "app/public/interactive/qr/{$deviceId}/{$date}";
            if (is_dir(storage_path($path))) {
                $files = glob(storage_path($path) . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }

                if (empty(glob(storage_path($path) . '/*'))) {
                    rmdir(storage_path($path));
                }
            }
        }
    }
}
