<?php

use App\Enums\Hrd\Signature\Template\DocumentFileStatus;
use App\Models\User;
use App\Services\RealtimeNotificationService;
use Modules\Hrd\Jobs\NotifyApprovalDocumentJob;
use Modules\Hrd\Models\MasterDocument;
use Modules\Hrd\Models\MasterDocumentFile;

use function Pest\Laravel\mock;

/**
 * The approval notification goes back to the creator of the template version, and must name the
 * template and version it refers to. SignatureService dispatches this job for both outcomes of
 * approvalMasterDocument, so the wording follows the version status. The realtime service is
 * mocked so the payload can be inspected without reaching Pusher.
 */
function captureApprovalNotification(MasterDocumentFile $version, User $creator): array
{
    $payload = null;

    mock(RealtimeNotificationService::class)
        ->shouldReceive('send')
        ->once()
        ->andReturnUsing(function ($recipients, $topic, $payload_) use (&$payload, $creator) {
            expect($recipients->id)->toBe($creator->id);
            $payload = $payload_;
        });

    (new NotifyApprovalDocumentJob($version))->handle();

    return $payload ?? [];
}

function makeReviewedVersion(User $creator, DocumentFileStatus $status, ?string $note = null): MasterDocumentFile
{
    $version = new MasterDocumentFile([
        'created_by' => $creator->id,
        'version' => 3,
        'status' => $status,
        'approval_note' => $note,
    ]);

    $version->setRelation('masterDocument', new MasterDocument([
        'uid' => 'md-uid-1',
        'name' => 'Employment Contract',
    ]));

    return $version;
}

it('notifies the version creator that the document template has been approved', function () {
    $creator = User::factory()->create();

    $payload = captureApprovalNotification(
        makeReviewedVersion($creator, DocumentFileStatus::Active),
        $creator
    );

    expect($payload['title'])->toBe(__('notification.documentTemplateApprovedTitle'))
        ->and($payload['message'])->toBe(__('notification.documentTemplateApprovedMessage', [
            'name' => 'Employment Contract',
            'version' => 3,
        ]))
        ->and($payload['message'])->toContain('Employment Contract')
        ->and($payload['action'])->toBe('document_template_approved')
        ->and($payload['data'])->toBe([
            'master_document_uid' => 'md-uid-1',
            'version' => 3,
            'reason' => null,
        ]);
});

it('notifies the version creator that the document template has been rejected, with the reason', function () {
    $creator = User::factory()->create();

    $payload = captureApprovalNotification(
        makeReviewedVersion($creator, DocumentFileStatus::Rejected, 'Wrong signature placement'),
        $creator
    );

    expect($payload['title'])->toBe(__('notification.documentTemplateRejectedTitle'))
        ->and($payload['message'])->toContain('Employment Contract')
        ->and($payload['message'])->toContain('Wrong signature placement')
        ->and($payload['action'])->toBe('document_template_rejected')
        ->and($payload['data']['reason'])->toBe('Wrong signature placement');
});

it('omits the reason sentence when a rejection carries no note', function () {
    $creator = User::factory()->create();

    $payload = captureApprovalNotification(
        makeReviewedVersion($creator, DocumentFileStatus::Rejected),
        $creator
    );

    expect($payload['message'])->toBe(__('notification.documentTemplateRejectedMessage', [
        'name' => 'Employment Contract',
        'version' => 3,
    ]))
        ->and($payload['data']['reason'])->toBeNull();
});

it('does nothing when the version creator no longer exists', function () {
    mock(RealtimeNotificationService::class)->shouldNotReceive('send');

    $version = new MasterDocumentFile(['created_by' => 0, 'version' => 1]);

    (new NotifyApprovalDocumentJob($version))->handle();
});
