<?php

namespace Modules\Inventory\Jobs;

use App\Enums\Company\ExportImportAreaType;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class InventoryExportHasBeenCompleted implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $user;
    private $filepath;

    /**
     * Create a new job instance.
     */
    public function __construct(object $user, string $filepath)
    {
        $this->user = $user;
        $this->filepath = $filepath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        logging('AFTER SHEET EXPORT INVENTORY', []);
        // notify user
        (new \App\Services\ExportImportService)->handleSuccessProcessing(payload: [
            'description' => 'Your inventory report file is ready. Please check your inbox to download the file.',
            'message' => '<p>Click <a href="'. $this->filepath .'" target="__blank">here</a> to download your inventory report</p>',
            'area' => 'inventory',
            'user_id' => $this->user->id,
            'type' => ExportImportAreaType::NewArea
        ], event: 'handle-export-import-notification-new');
    }
}
