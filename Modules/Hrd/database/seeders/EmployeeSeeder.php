<?php

namespace Modules\Hrd\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Modules\Company\Models\Position;
use Modules\Hrd\Models\Employee;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        \Modules\Hrd\Models\Employee::truncate();

        $range = range(0,15);

        $positions = Position::selectRaw('id')->whereIn('name', ['Marketing', 'Project Manager', 'Animator'])->get();
        $positionRange = collect($positions)->pluck('id')->toArray();
        
        $idNumberLength = 3;
        $prefix = 'DF';
        for ($a = 0; $a < count($range); $a++) {
            $numbering = $prefix . str_pad($a + 1, $idNumberLength, 0, STR_PAD_LEFT);
            $rand = rand(1000,9999);
            $phone = fake()->phoneNumber();
            $phone = str_replace('+', '', $phone);
            $phone = str_replace('-', '', $phone);
            $phone = str_replace(')', '', $phone);
            $phone = str_replace('(', '', $phone);
            $phone = str_replace(' ', '', $phone);
            $payload = [
                'name' => fake()->name(),
                'employee_id' => $numbering,
                'nickname' => fake()->firstName(),
                'email' => fake()->email(),
                'phone' => $phone,
                'id_number' => '888828282828' . $rand,
                'religion' => 'islam',
                'martial_status' => 'single',
                'address' => fake()->address(),
                'province_id' => null,
                'city_id' => null,
                'district_id' => null,
                'village_id' => null,
                'postal_code' => 65146,
                'current_address' => null,
                'blood_type' => 'B',
                'date_of_birth' => fake()->date(),
                'place_of_birth' => 'Malang',
                'dependant' => rand(1,4),
                'gender' => fake()->randomElement(['male', 'female']),
                'bank_detail' => json_encode([['bank_name' => 'mandiri', 'account_number' => 38838383838, 'account_holder_name' => fake()->firstName(), 'is_active' => true]]),
                'relation_contact' => json_encode(['name' => fake()->name(), 'phone' => $phone]),
                'education' => fake()->randomElement(['sma', 's1', 's2']),
                'education_name' => 'Univ Random',
                'education_major' => 'IT',
                'education_year' => fake()->randomElement(['2014', '2015', '2008']),
                'position_id' => fake()->randomElement($positionRange),
                'boss_id' => $a == 0 ? null : 1,
                'level_staff' => $a == 0 ? 'manager' : fake()->randomElement(['staff', 'lead', 'junior_staff']),
                'status' => fake()->randomElement(
                    collect(\App\Enums\Employee\Status::cases())->except([6,7,8])->toArray()
                ),
                'placement' => 'Surabaya',
                'join_date' => fake()->date(),
                'start_review_probation_date' => null,
                'probation_status' => null,
                'end_probation_date' => null,
                'company_name' => 'DFactory'
            ];   

            \Modules\Hrd\Models\Employee::create($payload);
        }

        Schema::enableForeignKeyConstraints();
    }
}
