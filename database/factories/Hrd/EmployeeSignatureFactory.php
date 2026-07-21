<?php

namespace Database\Factories\Hrd;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeeSignature;

/**
 * @extends Factory<EmployeeSignature>
 */
class EmployeeSignatureFactory extends Factory
{
    protected $model = EmployeeSignature::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'is_active' => true,
            'sign_path' => 'signatures/employees/'.fake()->uuid().'.png',
        ];
    }
}
