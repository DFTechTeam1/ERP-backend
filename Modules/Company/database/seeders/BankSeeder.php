<?php

namespace Modules\Company\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Company\Models\Bank;

class BankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $payload = [
            ['name' => 'BCA', 'bank_code' => 1],
            ['name' => 'Mandiri', 'bank_code' => 2],
            ['name' => 'BRI', 'bank_code' => 3],
            ['name' => 'CIMB', 'bank_code' => 4],
            ['name' => 'Commonwealth', 'bank_code' => 5],
            ['name' => 'BNI', 'bank_code' => 6],
            ['name' => 'Danamaon', 'bank_code' => 7],
            ['name' => 'Panin', 'bank_code' => 8],
            ['name' => 'Permata', 'bank_code' => 9],
            ['name' => 'BII', 'bank_code' => 10],
            ['name' => 'BTN', 'bank_code' => 11],
            ['name' => 'OCBC', 'bank_code' => 12],
            ['name' => 'Mega', 'bank_code' => 13],
            ['name' => 'UOB Indonesia', 'bank_code' => 14],
            ['name' => 'Bank Sinarmas', 'bank_code' => 15],
            ['name' => 'Bank Mayapada', 'bank_code' => 16],
            ['name' => 'ANZ', 'bank_code' => 17],
            ['name' => 'HCBC', 'bank_code' => 19],
            ['name' => 'Hana Bank', 'bank_code' => 19],
            ['name' => 'Bank DKI', 'bank_code' => 20],
            ['name' => 'Bank DBS Indonesia', 'bank_code' => 21],
            ['name' => 'Bank Sumut', 'bank_code' => 22],
            ['name' => 'Bank BJB', 'bank_code' => 23],
            ['name' => 'Bank Bukopin', 'bank_code' => 24],
            ['name' => 'HSBC', 'bank_code' => 25],
            ['name' => 'Citibank', 'bank_code' => 26],
            ['name' => 'Mandiri Syariah', 'bank_code' => 27],
            ['name' => 'Artha Graha', 'bank_code' => 28],
            ['name' => 'BRI Syariah', 'bank_code' => 29],
            ['name' => 'Bank BNI Syariah', 'bank_code' => 30],
            ['name' => 'Bank Muamalat', 'bank_code' => 31],
        ];

        foreach ($payload as $bankPayload) {
            Bank::create($bankPayload);
        }
    }
}
