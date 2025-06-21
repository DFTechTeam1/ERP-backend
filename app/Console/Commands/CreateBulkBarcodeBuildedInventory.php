<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Inventory\Models\CustomInventory;

class CreateBulkBarcodeBuildedInventory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:barcode-custom-inventory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create all barcode for custom inventory for the first time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $data = CustomInventory::selectRaw('id,build_series')
            ->get();

        foreach ($data as $inventory) {
            $barcode = generateBarcode($inventory->build_series, 'barcodes/custom_inventory/'.$inventory->id);
            if ($barcode) {
                $inventory->barcode = generateBarcode(config('app.frontend_url').'/ct/'.$inventory->build_series, 'barcodes/custom_inventory/'.$inventory->id.'/');
                $inventory->save();
            }
        }

        $this->info('Updated');
    }
}
