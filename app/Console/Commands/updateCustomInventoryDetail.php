<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Inventory\Models\CustomInventoryDetail;
use Modules\Inventory\Models\InventoryItem;

class updateCustomInventoryDetail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-custom-inventory-detail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Shift inventory_id value to inventory_item_id';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $inventories = CustomInventoryDetail::all();
        foreach ($inventories as $inventory) {
            $inventoryDetail = InventoryItem::select('id')
                ->where('inventory_id', $inventory->inventory_id)
                ->first();

            // update data
            $inventory->inventory_id = $inventoryDetail->id;
            $inventory->save();
        }

        $this->info('All data is updated');
    }
}
