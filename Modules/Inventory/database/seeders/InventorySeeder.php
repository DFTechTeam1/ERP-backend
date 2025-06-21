<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        \Modules\Inventory\Models\Inventory::truncate();

        $unit = \Modules\Inventory\Models\Unit::whereRaw("LOWER(name) = 'unit'")->first();

        $inventoryTypes = \Modules\Inventory\Models\InventoryType::all();
        $inventoryTypes = collect($inventoryTypes)->map(function ($item) {
            $item['name'] = strtolower($item->name);

            return $item;
        })->all();
        $monitor = collect($inventoryTypes)->where('name', 'monitor')->values()[0];
        $mouse = collect($inventoryTypes)->where('name', 'mouse')->values()[0];
        $keyboard = collect($inventoryTypes)->where('name', 'keyboard')->values()[0];
        $laptop = collect($inventoryTypes)->where('name', 'laptop')->values()[0];

        $brands = \Modules\Inventory\Models\Brand::all();
        $brands = collect($brands)->map(function ($item) {
            $item['name'] = strtolower($item->name);

            return $item;
        })->all();

        $lg = collect($brands)->where('name', 'lg')->values()[0];
        $viewsonic = collect($brands)->where('name', 'viewsonic')->values()[0];
        $rexus = collect($brands)->where('name', 'rexus')->values()[0];
        $xierra = collect($brands)->where('name', 'xierra')->values()[0];
        $logitech = collect($brands)->where('name', 'logitech')->values()[0];
        $asus = collect($brands)->where('name', 'asus')->values()[0];
        $omen = collect($brands)->where('name', 'omen')->values()[0];

        $supplier = \Modules\Inventory\Models\Supplier::all();
        $supplier = collect($supplier)->map(function ($item) {
            $item['name'] = strtolower($item->name);

            return $item;
        });
        $tokped = collect($supplier)->where('name', 'tokopedia')->values()[0];

        $data = [
            ['slug' => $monitor->slug, 'name' => '22MK430H', 'item_type' => $monitor->id, 'brand_id' => $lg->id, 'supplier_id' => null, 'description' => null, 'warranty' => null, 'year_of_purchase' => null, 'purchase_price' => '1850000', 'unit_id' => $unit->id, 'stock' => 1],
            ['slug' => $monitor->slug, 'name' => 'LG Monitor 22MN430', 'item_type' => $monitor->id, 'brand_id' => $lg->id, 'supplier_id' => null, 'description' => null, 'warranty' => null, 'year_of_purchase' => null, 'purchase_price' => '1474700', 'unit_id' => $unit->id, 'stock' => 1],
            ['slug' => $monitor->slug, 'name' => 'LG Monitor 22MK430H', 'item_type' => $monitor->id, 'brand_id' => $lg->id, 'supplier_id' => null, 'description' => null, 'warranty' => null, 'year_of_purchase' => null, 'purchase_price' => '1390000', 'unit_id' => $unit->id, 'stock' => 1],
            ['slug' => $monitor->slug, 'name' => 'LG Monitor 22MP68VQ-P', 'item_type' => $monitor->id, 'brand_id' => $lg->id, 'supplier_id' => null, 'description' => null, 'warranty' => null, 'year_of_purchase' => null, 'purchase_price' => '5585000', 'unit_id' => $unit->id, 'stock' => 1],
            ['slug' => $monitor->slug, 'name' => 'LG Monitor 20MP48A-P', 'item_type' => $monitor->id, 'brand_id' => $lg->id, 'supplier_id' => null, 'description' => null, 'warranty' => null, 'year_of_purchase' => null, 'purchase_price' => '1230000', 'unit_id' => $unit->id, 'stock' => 5],
            ['slug' => $monitor->slug, 'name' => 'LG Monitor 22MP58VQ-P', 'item_type' => $monitor->id, 'brand_id' => $lg->id, 'supplier_id' => null, 'description' => null, 'warranty' => null, 'year_of_purchase' => null, 'purchase_price' => '1545000', 'unit_id' => $unit->id, 'stock' => 6],
            ['slug' => $monitor->slug, 'name' => 'LG Monitor 20MP38HQ-B', 'item_type' => $monitor->id, 'brand_id' => $lg->id, 'supplier_id' => null, 'description' => null, 'warranty' => null, 'year_of_purchase' => null, 'purchase_price' => '1100000', 'unit_id' => $unit->id, 'stock' => 9],
            ['slug' => $monitor->slug, 'name' => 'LG Monitor 20MP38A-B', 'item_type' => $monitor->id, 'brand_id' => $lg->id, 'supplier_id' => null, 'description' => null, 'warranty' => null, 'year_of_purchase' => null, 'purchase_price' => '1100000', 'unit_id' => $unit->id, 'stock' => 1],
            ['slug' => $monitor->slug, 'name' => 'LG Monitor 22MK430H-B', 'item_type' => $monitor->id, 'brand_id' => $lg->id, 'supplier_id' => null, 'description' => null, 'warranty' => null, 'year_of_purchase' => null, 'purchase_price' => '1209000', 'unit_id' => $unit->id, 'stock' => 12],
            ['slug' => $monitor->slug, 'name' => 'LG Monitor 24MK40H-B', 'item_type' => $monitor->id, 'brand_id' => $lg->id, 'supplier_id' => null, 'description' => null, 'warranty' => null, 'year_of_purchase' => null, 'purchase_price' => '1209000', 'unit_id' => $unit->id, 'stock' => 1],
            ['slug' => $monitor->slug, 'name' => 'LG Monitor 24MP400-B', 'item_type' => $monitor->id, 'brand_id' => $lg->id, 'supplier_id' => null, 'description' => null, 'warranty' => null, 'year_of_purchase' => null, 'purchase_price' => '1300000', 'unit_id' => $unit->id, 'stock' => 1],
            ['slug' => $monitor->slug, 'name' => 'LG Monitor 22MN430-B', 'item_type' => $monitor->id, 'brand_id' => $lg->id, 'supplier_id' => null, 'description' => null, 'warranty' => null, 'year_of_purchase' => null, 'purchase_price' => '1490000', 'unit_id' => $unit->id, 'stock' => 1],
            ['slug' => $monitor->slug, 'name' => 'ViewSonic VX2416', 'item_type' => $monitor->id, 'brand_id' => $viewsonic->id, 'supplier_id' => $tokped->id, 'description' => null, 'warranty' => 2, 'year_of_purchase' => '2023', 'purchase_price' => '1509000', 'unit_id' => $unit->id, 'stock' => 5],
            ['slug' => $monitor->slug, 'name' => 'ViewSonic VX2480-SHDJ', 'item_type' => $monitor->id, 'brand_id' => $viewsonic->id, 'supplier_id' => null, 'description' => null, 'warranty' => null, 'year_of_purchase' => null, 'purchase_price' => '1509000', 'unit_id' => $unit->id, 'stock' => 1],
            ['slug' => $monitor->slug, 'name' => 'ViewSonic VX2417', 'item_type' => $monitor->id, 'brand_id' => $viewsonic->id, 'supplier_id' => $tokped->id, 'description' => null, 'warranty' => 2, 'year_of_purchase' => '2023', 'purchase_price' => '1509000', 'unit_id' => $unit->id, 'stock' => 1],
            ['slug' => $monitor->slug, 'name' => 'ViewSonic VX2418', 'item_type' => $monitor->id, 'brand_id' => $viewsonic->id, 'supplier_id' => $tokped->id, 'description' => null, 'warranty' => 2, 'year_of_purchase' => '2023', 'purchase_price' => '1509000', 'unit_id' => $unit->id, 'stock' => 1],
            ['slug' => $mouse->slug, 'name' => 'Rexus VR 1', 'item_type' => $mouse->id, 'brand_id' => $rexus->id, 'supplier_id' => null, 'description' => null, 'warranty' => null, 'year_of_purchase' => null, 'purchase_price' => '300000', 'unit_id' => $unit->id, 'stock' => 1],
            ['slug' => $mouse->slug, 'name' => 'Xierra G10', 'item_type' => $mouse->id, 'brand_id' => $xierra->id, 'supplier_id' => $tokped->id, 'description' => null, 'warranty' => null, 'year_of_purchase' => null, 'purchase_price' => '102000', 'unit_id' => $unit->id, 'stock' => 2],
            ['slug' => $mouse->slug, 'name' => 'Xierra RXM-G11', 'item_type' => $mouse->id, 'brand_id' => $xierra->id, 'supplier_id' => null, 'description' => null, 'warranty' => null, 'year_of_purchase' => null, 'purchase_price' => '160000', 'unit_id' => $unit->id, 'stock' => 3],
            ['slug' => $mouse->slug, 'name' => 'Q20-WIRELESS', 'item_type' => $mouse->id, 'brand_id' => $rexus->id, 'supplier_id' => null, 'description' => null, 'warranty' => null, 'year_of_purchase' => null, 'purchase_price' => '50000', 'unit_id' => $unit->id, 'stock' => 1],
            ['slug' => $keyboard->slug, 'name' => 'Logitech K120', 'item_type' => $keyboard->id, 'brand_id' => $logitech->id, 'supplier_id' => null, 'description' => null, 'warranty' => null, 'year_of_purchase' => null, 'purchase_price' => '150000', 'unit_id' => $unit->id, 'stock' => 2],
            ['slug' => $keyboard->slug, 'name' => 'Logitech MK345 Wireless 1 set (keyboard + Mouse', 'item_type' => $keyboard->id, 'brand_id' => $logitech->id, 'supplier_id' => null, 'description' => null, 'warranty' => null, 'year_of_purchase' => null, 'purchase_price' => '150000', 'unit_id' => $unit->id, 'stock' => 3],
            ['slug' => $laptop->slug, 'name' => 'Asus ROG G16', 'item_type' => $laptop->id, 'brand_id' => $asus->id, 'supplier_id' => $tokped->id, 'description' => null, 'warranty' => 2, 'year_of_purchase' => '2023', 'purchase_price' => '35000000', 'unit_id' => $unit->id, 'stock' => 3],
            ['slug' => $laptop->slug, 'name' => 'Asus Vivobook', 'item_type' => $laptop->id, 'brand_id' => $asus->id, 'supplier_id' => $tokped->id, 'description' => null, 'warranty' => 2, 'year_of_purchase' => '2023', 'purchase_price' => '12000000', 'unit_id' => $unit->id, 'stock' => 5],
            ['slug' => $laptop->slug, 'name' => 'Omen 16', 'item_type' => $laptop->id, 'brand_id' => $omen->id, 'supplier_id' => $tokped->id, 'description' => null, 'warranty' => 2, 'year_of_purchase' => '2023', 'purchase_price' => '29999999', 'unit_id' => $unit->id, 'stock' => 1],
        ];

        foreach ($data as $d) {
            $d['purchase_price'] = (float) $d['purchase_price'];

            $inventory = \Modules\Inventory\Models\Inventory::create(collect($d)->except(['slug'])->toArray());

            $dividerCode = '-';
            for ($a = 0; $a < $d['stock']; $a++) {
                $inventoryCode = rand(100, 900).$dividerCode.$d['slug'].$dividerCode.$a + 1;
                \Modules\Inventory\Models\InventoryItem::create([
                    'inventory_id' => $inventory->id,
                    'inventory_code' => $inventoryCode,
                    'status' => \App\Enums\Inventory\InventoryStatus::InUse->value,
                    'current_location' => \App\Enums\Inventory\Location::InWarehouse->value,
                    'user_id' => null,
                ]);
            }
        }

        Schema::enableForeignKeyConstraints();
    }
}
