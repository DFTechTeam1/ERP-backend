<?php

namespace App\Jobs\Cache;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InventoriesCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // inventoriesCache
        \Illuminate\Support\Facades\Cache::rememberForever('inventoriesCache', function () {
            $query = \Modules\Inventory\Models\Inventory::query();
            $query->select('*')
                ->with([
                    'images',
                    'image',
                    'items',
                    'brand',
                    'itemTypeRelation',
                    'unit'
                ]);

            $data = $query->get();

            $data = collect($data)->map(function ($item) {
                $item['brand_name'] = null;
                if ($item->brand) {
                    $item['brand_name'] = $item->brand->name;
                }

                $item['unit_name'] = null;
                if ($item->unit) {
                    $item['unit_name'] = $item->unit->name;
                }

                $item['item_type_name'] = null;
                if ($item->itemTypeRelation) {
                    $item['item_type_name'] = $item->itemTypeRelation->name;
                }

                return $item;
            })->values();

            return $data;
        });
    }
}
