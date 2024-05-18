<?php

namespace Modules\Addon\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class AddonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);
        Schema::disableForeignKeyConstraints();
        \Modules\Addon\Models\Addon::truncate();

        
        $range = range(0,20);
        $data = [];
        for ($a = 0; $a < count($range); $a++) {
            $data[] = ['name' => 'addon ' . $a+1, 'preview_img' => 'noimage.png', 'tutorial_video' => 'tutorial', 'main_file' => 'Main File', 'description' => 'Lorem ipsum dolor sit, amet consectetur adipisicing elit. Neque molestias nesciunt animi sed. Neque animi numquam optio a? Eveniet fugiat necessitatibus minima magnam expedita dolorum neque, beatae aliquam veniam impedit.'];
        }

        foreach ($data as $d) {
            \Modules\Addon\Models\Addon::create($d);
        }

        Schema::enableForeignKeyConstraints();
    }
}
