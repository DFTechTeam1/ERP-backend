<?php

namespace Database\Factories\Hrd;

use App\Enums\Hrd\Signature\Template\Status;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeeDocument;

/**
 * @extends Factory<EmployeeDocument>
 */
class EmployeeDocumentFactory extends Factory
{
    protected $model = EmployeeDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'status' => Status::NeedSign,
            'signers_detail' => null,
            'total_signer' => 1,
            'document_snapshot' => null,
            'document_path' => 'employees/documents/'.fake()->uuid().'.docx',
            'document_type_id' => null,
        ];
    }
}
