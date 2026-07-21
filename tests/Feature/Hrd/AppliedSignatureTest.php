<?php

use App\Enums\Hrd\Signature\SignatureTaskStatus;
use App\Enums\Hrd\Signature\Template\Status;
use App\Models\User;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeeDocument;
use Modules\Hrd\Models\EmployeeSignature;
use Modules\Hrd\Models\EmployeeSignatureTask;
use Modules\Hrd\Services\SignatureService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

/**
 * Create an employee with a linked user and return both.
 *
 * @return array{0: Employee, 1: User}
 */
function makeSigner(): array
{
    $employee = Employee::factory()->withUser()->create();
    $user = User::where('employee_id', $employee->id)->firstOrFail();

    return [$employee, $user];
}

it('records the signature reference and marks the task signed instead of baking it', function () {
    [$employee, $user] = makeSigner();

    $signature = EmployeeSignature::factory()->create(['employee_id' => $employee->id]);
    $document = EmployeeDocument::factory()->create(['total_signer' => 1]);
    $task = EmployeeSignatureTask::factory()->create([
        'employee_document_id' => $document->id,
        'employee_id' => $employee->id,
        'order' => 1,
    ]);

    actingAs($user);

    $response = app(SignatureService::class)
        ->applySignatureToDocument($signature->uid, $document->uid);

    expect($response['error'])->toBeFalse();

    assertDatabaseHas('employee_signature_tasks', [
        'id' => $task->id,
        'employee_signature_id' => $signature->id,
        'status' => SignatureTaskStatus::Signed->value,
    ]);

    // Last remaining signer signed -> document is completed.
    assertDatabaseHas('employee_documents', [
        'id' => $document->id,
        'status' => Status::Completed->value,
    ]);
});

it('lets a signer replace their own signature while no later signer has signed', function () {
    [$employee, $user] = makeSigner();
    [$laterEmployee] = makeSigner();

    $firstSignature = EmployeeSignature::factory()->create(['employee_id' => $employee->id]);
    $newSignature = EmployeeSignature::factory()->create(['employee_id' => $employee->id]);
    $document = EmployeeDocument::factory()->create(['total_signer' => 2]);

    $task = EmployeeSignatureTask::factory()->signed()->create([
        'employee_document_id' => $document->id,
        'employee_id' => $employee->id,
        'employee_signature_id' => $firstSignature->id,
        'order' => 1,
    ]);
    EmployeeSignatureTask::factory()->create([
        'employee_document_id' => $document->id,
        'employee_id' => $laterEmployee->id,
        'order' => 2,
    ]);

    actingAs($user);

    $response = app(SignatureService::class)
        ->updateAppliedSignature($newSignature->uid, $document->uid);

    expect($response['error'])->toBeFalse();

    assertDatabaseHas('employee_signature_tasks', [
        'id' => $task->id,
        'employee_signature_id' => $newSignature->id,
    ]);
});

it('refuses to replace a signature once a later signer has signed', function () {
    [$employee, $user] = makeSigner();
    [$laterEmployee] = makeSigner();

    $firstSignature = EmployeeSignature::factory()->create(['employee_id' => $employee->id]);
    $newSignature = EmployeeSignature::factory()->create(['employee_id' => $employee->id]);
    $document = EmployeeDocument::factory()->create(['total_signer' => 2]);

    $task = EmployeeSignatureTask::factory()->signed()->create([
        'employee_document_id' => $document->id,
        'employee_id' => $employee->id,
        'employee_signature_id' => $firstSignature->id,
        'order' => 1,
    ]);
    EmployeeSignatureTask::factory()->signed()->create([
        'employee_document_id' => $document->id,
        'employee_id' => $laterEmployee->id,
        'order' => 2,
    ]);

    actingAs($user);

    $response = app(SignatureService::class)
        ->updateAppliedSignature($newSignature->uid, $document->uid);

    expect($response['error'])->toBeTrue();

    // Signature reference is untouched.
    assertDatabaseHas('employee_signature_tasks', [
        'id' => $task->id,
        'employee_signature_id' => $firstSignature->id,
    ]);
});

it('refuses to replace a signature the signer has not applied yet', function () {
    [$employee, $user] = makeSigner();

    $signature = EmployeeSignature::factory()->create(['employee_id' => $employee->id]);
    $document = EmployeeDocument::factory()->create(['total_signer' => 1]);
    EmployeeSignatureTask::factory()->create([
        'employee_document_id' => $document->id,
        'employee_id' => $employee->id,
        'order' => 1,
    ]);

    actingAs($user);

    $response = app(SignatureService::class)
        ->updateAppliedSignature($signature->uid, $document->uid);

    expect($response['error'])->toBeTrue();
});

it('refuses to delete a signature that is applied to a document', function () {
    [$employee, $user] = makeSigner();

    $signature = EmployeeSignature::factory()->create(['employee_id' => $employee->id]);
    $document = EmployeeDocument::factory()->create(['total_signer' => 1]);
    EmployeeSignatureTask::factory()->signed()->create([
        'employee_document_id' => $document->id,
        'employee_id' => $employee->id,
        'employee_signature_id' => $signature->id,
        'order' => 1,
    ]);

    actingAs($user);

    $response = app(SignatureService::class)
        ->deleteEmployeeSignature($signature->uid);

    expect($response['error'])->toBeTrue();

    assertDatabaseHas('employee_signatures', [
        'id' => $signature->id,
    ]);
});
