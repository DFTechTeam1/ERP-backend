<?php

namespace Database\Factories\Hrd;

use App\Enums\Employee\MartialStatus;
use App\Enums\Employee\PtkpStatus;
use App\Enums\Employee\Religion;
use App\Enums\Employee\SalaryType;
use App\Enums\Employee\Status;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Company\Models\Branch;
use Modules\Company\Models\IndonesiaCity;
use Modules\Company\Models\IndonesiaDistrict;
use Modules\Company\Models\IndonesiaVillage;
use Modules\Company\Models\Position;
use Modules\Company\Models\Province;
use Modules\Hrd\Models\Employee;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    protected function formatCollectionEnum(array $data)
    {
        return collect($data)->map(function ($item) {
            return $item->value;
        })->toArray();
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $range = range(1,54);
        $range = collect($range)->map(function ($item) {
            $idNumberLength = 3;
            $prefix = 'DF';
            $numbering = $prefix . str_pad($item, $idNumberLength, 0, STR_PAD_LEFT);

            return $numbering;
        })->toArray();

        $firstName = fake()->firstName();
        $name = $firstName . ' ' . fake()->lastName();

        $religions = $this->formatCollectionEnum(Religion::cases());
        $martialStatus = $this->formatCollectionEnum(MartialStatus::cases());

        return [
            'name' => $name,
            'employee_id' => 'DF' . fake()->randomNumber(3, true),
            'nickname' => $firstName,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->randomNumber(9),
            'id_number' => fake()->unique()->randomNumber(8, true) . fake()->unique()->randomNumber(8, true),
            'religion' => fake()->randomElement($religions),
            'martial_status' => fake()->randomElement($martialStatus),
            'address' => fake()->address(),
            // 'province_id' => Province::factory()->create()->code,
            // 'city_id' => IndonesiaCity::factory()->create()->code,
            // 'district_id' => IndonesiaDistrict::factory()->create()->code,
            // 'village_id' => IndonesiaVillage::factory()->create()->code,
            'postal_code' => 1234,
            'current_address' => fake()->address(),
            'blood_type' => fake()->bloodType(),
            'date_of_birth' => fake()->date('Y-m-d', '1996-01-01'),
            'place_of_birth' => 'Indonesia',
            'gender' => fake()->randomElement(['male', 'female']),
            'bank_detail' => json_encode([
                [
                    'bank_name' => 'name',
                    'account_number' => fake()->randomNumber(9, true),
                    'account_holder_name' => $firstName,
                    'is_active' => true,
                ]
            ]),
            'relation_contact' => json_encode([
                'name' => fake()->name(),
                'phone' => fake()->phoneNumber(),
                'relation' => 'brother'
            ]),
            'education' => fake()->randomElement(['s1', 'sma', 'diploma']),
            'education_name' => 'Education',
            'education_major' => 'master',
            'education_year' => 2024,
            'position_id' => Position::factory(),
            'boss_id' => null,
            'level_staff' => fake()->randomElement(['manager', 'lead', 'staff']),
            'status' => Status::Permanent->value,
            'join_date' => fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
            'start_review_probation_date' => null,
            'probation_status' => null,
            'end_probation_date' => null,
            'basic_salary' => '20000000',
            'salary_type' => SalaryType::Monthly->value,
            'ptkp_status' => PtkpStatus::K0->value,
            'branch_id' => Branch::factory()
        ];
    }
}
