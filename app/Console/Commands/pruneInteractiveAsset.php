<?php

namespace App\Console\Commands;

use App\Models\InteractiveImage;
use Carbon\Carbon;
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
        // get all data
        $dateMax = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        $images = InteractiveImage::whereRaw("created_at <= '{$dateMax}'")
            ->get();

        foreach ($images as $image) {
            if (is_file(storage_path("app/public/{$image->qrcode}"))) {
                unlink(storage_path("app/public/{$image->qrcode}"));
            }
            if (is_file(storage_path("app/public/{$image->filepath}"))) {
                unlink(storage_path("app/public/{$image->filepath}"));
            }

            InteractiveImage::where('id', $image->id)
                ->delete();
        }
    }
}
