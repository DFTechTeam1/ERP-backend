<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);
        Schema::disableForeignKeyConstraints();
        
        \Modules\Inventory\Models\Brand::truncate();

        $brands = [
            ['name' => 'Adata'],
            ['name' => 'Samsung'],
            ['name' => 'Seagate'],
            ['name' => 'LG'],
            ['name' => 'ViewSonic'],
            ['name' => 'Rexus'],
            ['name' => 'Xierra'],
            ['name' => 'Logitech'],
            ['name' => 'Be Quite'],
            ['name' => 'Corsair'],
            ['name' => 'PSU'],
            ['name' => 'Seasonic'],
            ['name' => 'Super Flower'],
            ['name' => 'Team'],
            ['name' => 'Trident'],
            ['name' => 'Geil'],
            ['name' => 'G.Skill'],
            ['name' => 'Klevv'],
            ['name' => 'NVIDIA'],
            ['name' => 'GALAc'],
            ['name' => 'MSI'],
            ['name' => 'AMD'],
            ['name' => 'GigaByte'],
            ['name' => 'Zotac'],
            ['name' => 'Asus'],
            ['name' => 'Deepcool'],
            ['name' => 'Cooler Master'],
            ['name' => 'Omen'],
            ['name' => 'Lenovo'],
            ['name' => 'APC'],
            ['name' => 'Matvel'],
            ['name' => 'Peavey'],
            ['name' => 'Taffware'],
            ['name' => 'TP-LINK'],
            ['name' => 'Traktor'],
            ['name' => 'UGREEN'],
            ['name' => 'Vention'],
            ['name' => 'Wacon'],
            ['name' => 'Oricco'],
            ['name' => 'Avermedia'],
            ['name' => 'Epson'],
            ['name' => 'Dell'],
            ['name' => 'D-LINK'],
            ['name' => 'HP'],
            ['name' => 'Sony'],
            ['name' => 'Acasis'],
            ['name' => 'ROG'],
            ['name' => 'Xiaomi'],
            ['name' => 'Native Instrument'],
        ];

        foreach ($brands as $brand) {
            \Modules\Inventory\Models\Brand::create($brand);
        }

        Schema::enableForeignKeyConstraints();
    }
}
