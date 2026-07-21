<?php

namespace Database\Factories\Hrd;

use App\Enums\Hrd\Signature\SignatureTaskStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeeDocument;
use Modules\Hrd\Models\EmployeeSignatureTask;

/**
 * @extends Factory<EmployeeSignatureTask>
 */
class EmployeeSignatureTaskFactory extends Factory
{
    protected $model = EmployeeSignatureTask::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'employee_document_id' => EmployeeDocument::factory(),
            'employee_signature_id' => null,
            'order' => 1,
            'status' => SignatureTaskStatus::Waiting,
        ];
    }

    public function signed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SignatureTaskStatus::Signed,
            'signed_at' => now(),
        ]);
    }
}
