<?php

use App\Enums\Hrd\Signature\Template\Status as DocumentStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use Modules\Hrd\Models\DocumentType;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeeDocument;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * Deleting a generated document is a privileged, soft-delete operation restricted to non-completed
 * documents. These tests cover access control, the completed-document guard, the bulk summary, and
 * that a soft delete frees the duplicate slot so a new document can be generated again.
 */
function actAsDocumentManager(): User
{
    $employee = Employee::factory()->withUser()->create();
    $user = User::where('employee_id', $employee->id)->first();
    $role = Role::firstOrCreate(['name' => BaseRole::Hrd->value, 'guard_name' => 'sanctum']);
    $user->assignRole($role);
    actingAs($user);

    return $user;
}

function deletionDocumentType(): DocumentType
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

$base = '/api/signatures';

it('lets a privileged user soft delete a non-completed document', function () use ($base) {
    actAsDocumentManager();
    $document = EmployeeDocument::factory()->create(['status' => DocumentStatus::NeedSign]);

    $this->deleteJson($base.'/documents/'.$document->uid)->assertStatus(201);

    expect(EmployeeDocument::find($document->id))->toBeNull()
        ->and(EmployeeDocument::withTrashed()->find($document->id)->deleted_at)->not->toBeNull();
});

it('refuses to delete a completed document', function () use ($base) {
    actAsDocumentManager();
    $document = EmployeeDocument::factory()->create(['status' => DocumentStatus::Completed]);

    $this->deleteJson($base.'/documents/'.$document->uid)->assertStatus(400);

    expect(EmployeeDocument::find($document->id))->not->toBeNull();
});

it('returns a handled error when deleting an unknown document', function () use ($base) {
    actAsDocumentManager();

    $this->deleteJson($base.'/documents/unknown-uid')->assertStatus(400);
});

it('forbids a non-privileged user from deleting a document', function () use ($base) {
    actAsEmployeeUser();
    $document = EmployeeDocument::factory()->create(['status' => DocumentStatus::NeedSign]);

    $this->deleteJson($base.'/documents/'.$document->uid)->assertStatus(403);

    expect(EmployeeDocument::find($document->id))->not->toBeNull();
});

it('bulk deletes non-completed documents and skips completed or missing ones', function () use ($base) {
    actAsDocumentManager();
    $needSign = EmployeeDocument::factory()->create(['status' => DocumentStatus::NeedSign]);
    $awaiting = EmployeeDocument::factory()->create(['status' => DocumentStatus::Awaiting]);
    $completed = EmployeeDocument::factory()->create(['status' => DocumentStatus::Completed]);

    $this->deleteJson($base.'/documents/bulk', [
        'uids' => [$needSign->uid, $awaiting->uid, $completed->uid, 'missing-uid'],
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.requested', 4)
        ->assertJsonPath('data.deleted', 2)
        ->assertJsonPath('data.skipped', 2);

    expect(EmployeeDocument::find($needSign->id))->toBeNull()
        ->and(EmployeeDocument::find($awaiting->id))->toBeNull()
        ->and(EmployeeDocument::find($completed->id))->not->toBeNull();
});

it('forbids a non-privileged user from bulk deleting', function () use ($base) {
    actAsEmployeeUser();
    $document = EmployeeDocument::factory()->create(['status' => DocumentStatus::NeedSign]);

    $this->deleteJson($base.'/documents/bulk', ['uids' => [$document->uid]])->assertStatus(403);

    expect(EmployeeDocument::find($document->id))->not->toBeNull();
});

it('frees the duplicate slot so a new document can be generated after deletion', function () {
    $employee = Employee::factory()->create();
    $type = deletionDocumentType();

    $first = EmployeeDocument::factory()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $type->id,
        'status' => DocumentStatus::NeedSign,
    ]);

    $first->delete();

    $second = EmployeeDocument::factory()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $type->id,
        'status' => DocumentStatus::NeedSign,
    ]);

    expect($second->exists)->toBeTrue();
});
