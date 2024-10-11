<?php

namespace Modules\Inventory\Console;

use Illuminate\Console\Command;
use Modules\Inventory\Models\Inventory;
use Modules\Inventory\Models\InventoryItem;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class SyncInventoryToNewItemFormat extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sync:inventory-to-new-item-format';

    /**
     * The console command description.
     */
    protected $description = 'Command description.';

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
        $allInventories = Inventory::selectRaw('id,purchase_price,warranty,year_of_purchase')
            ->get();

        foreach ($allInventories as $inventory) {
            InventoryItem::where('inventory_id', $inventory->id)
                ->update([
                    'warranty' => $inventory->warranty,
                    'purchase_price' => $inventory->purchase_price,
                    'year_of_purchase' => $inventory->year_of_purchase,
                ]);
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
