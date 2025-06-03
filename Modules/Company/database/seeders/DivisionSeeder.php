<?php

namespace Modules\Company\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Modules\Company\Models\Division;

class DivisionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Division::truncate();

        $divisions = [
            [
                'name' => 'General',
                'parent_id' => null,
            ],
            [
                'name' => 'HR',
                'parent_id' => 1,
            ],
            [
                'name' => 'Finance',
                'parent_id' => 1,
            ],
            [
                'name' => 'IT',
                'parent_id' => 1,
            ],
            [
                'name' => 'Marketing',
                'parent_id' => 1,
            ],
            [
                'name' => 'Operational',
                'parent_id' => null,
            ],
            [
                'name' => 'Production',
                'parent_id' => 6,
            ],
            [
                'name' => 'Entertainment',
                'parent_id' => 6,
            ],
        ];

        foreach ($divisions as $division) {
            Division::create($division);
        }

        Schema::enableForeignKeyConstraints();
    }
}
