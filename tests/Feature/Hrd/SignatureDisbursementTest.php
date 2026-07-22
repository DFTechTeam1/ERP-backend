<?php

use App\Enums\Employee\Status;
use App\Enums\Hrd\Signature\GenerateDocument\AssignTo;
use App\Enums\Hrd\Signature\Template\Status as DocumentStatus;
use App\Models\User;
use Illuminate\Database\QueryException;
use Modules\Company\Models\PositionBackup;
use Modules\Hrd\Models\DocumentType;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeeDocument;
use Modules\Hrd\Repository\EmployeeRepository;

/**
 * The audience resolution behind signature disbursement (all / by division / by position) is the
 * core business logic that decides which employees receive a generated document. It is exercised
 * directly against EmployeeRepository::getForSignatureDisbursement so it can be asserted without
 * the docx generation pipeline.
 */
function makePositionInDivision(int $divisionId): PositionBackup
{
    return PositionBackup::factory()->create(['division_id' => $divisionId]);
}

it('disburses to every active employee, excluding inactive ones', function () {
    $repo = app(EmployeeRepository::class);

    $position = makePositionInDivision(divisionId: 501);

    $active = Employee::factory()->create([
        'position_id' => $position->id,
        'status' => Status::Permanent->value,
    ]);
    $inactive = Employee::factory()->create([
        'position_id' => $position->id,
        'status' => Status::Inactive->value,
    ]);

    $ids = $repo->getForSignatureDisbursement(['id'], AssignTo::All)->pluck('id');

    expect($ids)->toContain($active->id)
        ->and($ids)->not->toContain($inactive->id);
});

it('disburses only to active employees whose position belongs to the division', function () {
    $repo = app(EmployeeRepository::class);

    $targetDivisionId = 601;
    $otherDivisionId = 602;

    $positionInDivision = makePositionInDivision(divisionId: $targetDivisionId);
    $positionOutside = makePositionInDivision(divisionId: $otherDivisionId);

    $inDivision = Employee::factory()->create([
        'position_id' => $positionInDivision->id,
        'status' => Status::Contract->value,
    ]);
    $inDivisionInactive = Employee::factory()->create([
        'position_id' => $positionInDivision->id,
        'status' => Status::Inactive->value,
    ]);
    $outside = Employee::factory()->create([
        'position_id' => $positionOutside->id,
        'status' => Status::Permanent->value,
    ]);

    $ids = $repo->getForSignatureDisbursement(['id'], AssignTo::Division, $targetDivisionId)->pluck('id');

    expect($ids)->toContain($inDivision->id)
        ->and($ids)->not->toContain($outside->id)
        ->and($ids)->not->toContain($inDivisionInactive->id);
});

it('disburses only to active employees holding the selected position', function () {
    $repo = app(EmployeeRepository::class);

    $targetPosition = makePositionInDivision(divisionId: 701);
    $otherPosition = makePositionInDivision(divisionId: 701);

    $holder = Employee::factory()->create([
        'position_id' => $targetPosition->id,
        'status' => Status::Probation->value,
    ]);
    $otherHolder = Employee::factory()->create([
        'position_id' => $otherPosition->id,
        'status' => Status::Permanent->value,
    ]);

    $ids = $repo->getForSignatureDisbursement(['id'], AssignTo::Position, null, $targetPosition->id)->pluck('id');

    expect($ids)->toContain($holder->id)
        ->and($ids)->not->toContain($otherHolder->id);
});

// ---------------------------------------------------------------------------
// Duplicate guard: the DB rejects a second in-progress document for the same
// employee + document type, while still allowing one after completion.
// ---------------------------------------------------------------------------
function makeDocumentType(): DocumentType
{
    return DocumentType::create([
        'name' => 'Type '.fake()->unique()->uuid(),
        'code' => 'C'.fake()->unique()->numerify('######'),
        'retention' => 1,
        'default_number_of_signers' => 1,
        'status' => 1,
        'category' => 'hr',
        'created_by' => User::factory()->create()->id,
    ]);
}

it('rejects a second in-progress document for the same employee and type', function () {
    $employee = Employee::factory()->create();
    $type = makeDocumentType();

    EmployeeDocument::factory()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $type->id,
        'status' => DocumentStatus::NeedSign,
    ]);

    expect(fn () => EmployeeDocument::factory()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $type->id,
        'status' => DocumentStatus::Awaiting,
    ]))->toThrow(QueryException::class);
});

it('allows a fresh document once the previous one is completed', function () {
    $employee = Employee::factory()->create();
    $type = makeDocumentType();

    EmployeeDocument::factory()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $type->id,
        'status' => DocumentStatus::Completed,
    ]);

    $fresh = EmployeeDocument::factory()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $type->id,
        'status' => DocumentStatus::NeedSign,
    ]);

    expect($fresh->exists)->toBeTrue();
});
