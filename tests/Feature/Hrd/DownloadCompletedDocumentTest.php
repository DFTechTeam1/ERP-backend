<?php

use App\Enums\Hrd\Signature\SignatureTaskStatus;
use App\Enums\Hrd\Signature\Template\Status;
use App\Models\User;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeeDocument;
use Modules\Hrd\Models\EmployeeSignatureTask;
use Modules\Hrd\Services\SignatureService;

use function Pest\Laravel\actingAs;

/**
 * Create an employee with a linked user and return both.
 *
 * @return array{0: Employee, 1: User}
 */
function makeDownloadSigner(): array
{
    $employee = Employee::factory()->withUser()->create();
    $user = User::where('employee_id', $employee->id)->firstOrFail();

    return [$employee, $user];
}

it('refuses to download a document that is not completed yet', function () {
    [$employee, $user] = makeDownloadSigner();

    $document = EmployeeDocument::factory()->create([
        'status' => Status::NeedSign,
        'total_signer' => 1,
    ]);
    EmployeeSignatureTask::factory()->create([
        'employee_document_id' => $document->id,
        'employee_id' => $employee->id,
        'status' => SignatureTaskStatus::Waiting,
        'order' => 1,
    ]);

    actingAs($user);

    $response = app(SignatureService::class)
        ->downloadCompletedDocument($document->uid);

    expect($response['error'])->toBeTrue();
});

it('refuses to download for a user who is neither privileged nor a signer', function () {
    [, $outsider] = makeDownloadSigner();
    [$signer] = makeDownloadSigner();

    $document = EmployeeDocument::factory()->create([
        'status' => Status::Completed,
        'total_signer' => 1,
    ]);
    EmployeeSignatureTask::factory()->signed()->create([
        'employee_document_id' => $document->id,
        'employee_id' => $signer->id,
        'order' => 1,
    ]);

    actingAs($outsider);

    $response = app(SignatureService::class)
        ->downloadCompletedDocument($document->uid);

    expect($response['error'])->toBeTrue();
    expect($response['code'])->toBe(403);
});
