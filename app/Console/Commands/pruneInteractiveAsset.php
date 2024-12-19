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
        $images = InteractiveImage::whereRaw("created_at < NOW() - INTERVAL 5 MINUTE")
            ->get();

        logging('all images to be deleted', $images->toArray());

        foreach ($images as $image) {
            logging('image to delete: ', $image->toArray());
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
