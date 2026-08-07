<?php

use App\Enums\Hrd\Signature\Template\DocumentFileStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use App\Services\RealtimeNotificationService;
use Modules\Hrd\Jobs\NotifyGeneratedDocumentJob;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\MasterDocument;
use Modules\Hrd\Models\MasterDocumentFile;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\mock;

/**
 * A newly submitted template version sits in pending review until a director acts on it, so every
 * director is told what is waiting and who sent it. The realtime service is mocked so the payload
 * can be inspected without reaching Pusher.
 */
function makeDirector(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::firstOrCreate(['name' => BaseRole::Director->value, 'guard_name' => 'sanctum']));

    return $user;
}

function makeTemplateAwaitingApproval(?User $submitter = null): MasterDocument
{
    $document = MasterDocument::create(['name' => 'Employment Contract']);

    MasterDocumentFile::create([
        'master_document_id' => $document->id,
        'path' => 'templates/'.uniqid().'.docx',
        'file_type' => 'docx',
        'status' => DocumentFileStatus::PendingReview,
        'created_by' => $submitter?->id,
    ]);

    return $document;
}

it('tells every director which template version is waiting for review', function () {
    $directors = collect([makeDirector(), makeDirector()]);
    $document = makeTemplateAwaitingApproval();

    $notified = collect();
    $payload = null;

    mock(RealtimeNotificationService::class)
        ->shouldReceive('send')
        ->twice()
        ->andReturnUsing(function ($recipients, $topic, $payload_) use (&$notified, &$payload) {
            $notified->push($recipients->id);
            $payload = $payload_;
        });

    (new NotifyGeneratedDocumentJob($document))->handle();

    expect($notified->sort()->values()->all())->toBe($directors->pluck('id')->sort()->values()->all())
        ->and($payload['title'])->toBe(__('notification.documentTemplatePendingApprovalTitle'))
        ->and($payload['message'])->toBe(__('notification.documentTemplatePendingApprovalMessage', [
            'name' => 'Employment Contract',
            'version' => 1,
        ]))
        ->and($payload['action'])->toBe('document_template_pending_approval')
        ->and($payload['data'])->toBe([
            'master_document_uid' => $document->uid,
            'version' => '1',
        ]);
});

it('names the submitter when the version carries one', function () {
    makeDirector();

    $employee = Employee::factory()->withUser()->create(['name' => 'Josiah']);
    $submitter = User::where('employee_id', $employee->id)->first();

    $payload = null;
    mock(RealtimeNotificationService::class)
        ->shouldReceive('send')
        ->once()
        ->andReturnUsing(function ($recipients, $topic, $payload_) use (&$payload) {
            $payload = $payload_;
        });

    (new NotifyGeneratedDocumentJob(makeTemplateAwaitingApproval($submitter)))->handle();

    expect($payload['message'])->toContain('Josiah')
        ->and($payload['message'])->toContain('Employment Contract');
});

it('notifies nobody when the template has no pending version', function () {
    makeDirector();

    mock(RealtimeNotificationService::class)->shouldNotReceive('send');

    $document = MasterDocument::create(['name' => 'Employment Contract']);

    (new NotifyGeneratedDocumentJob($document))->handle();
});

it('notifies nobody when the director role has no holder', function () {
    Role::firstOrCreate(['name' => BaseRole::Director->value, 'guard_name' => 'sanctum']);
    User::query()->whereHas('roles', fn ($query) => $query->where('name', BaseRole::Director->value))->delete();

    mock(RealtimeNotificationService::class)->shouldNotReceive('send');

    (new NotifyGeneratedDocumentJob(makeTemplateAwaitingApproval()))->handle();
});
