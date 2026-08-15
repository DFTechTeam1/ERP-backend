<?php

use App\Enums\Hrd\Signature\Template\DocumentFileStatus;
use App\Enums\Hrd\Signature\Template\Status;
use App\Models\User;
use Database\Seeders\RolePermissionSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Modules\Company\Models\DivisionBackup;
use Modules\Hrd\Contracts\DocumentPdfConverter;
use Modules\Hrd\Jobs\NotifyApprovalDocumentJob;
use Modules\Hrd\Jobs\NotifyGeneratedDocumentJob;
use Modules\Hrd\Models\DocumentType;
use Modules\Hrd\Models\DocumentTypeSigner;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeeDocument;
use Modules\Hrd\Models\EmployeeSignature;
use Modules\Hrd\Models\EmployeeSignatureTask;
use Modules\Hrd\Models\MasterDocument;
use Modules\Hrd\Models\MasterDocumentFile;
use Modules\Hrd\Models\MasterDocumentSigner;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

/**
 * The signature endpoints all live under the auth.session-protected /api/signatures group and
 * delegate to SignatureService. This suite covers each controller method at the level that is
 * reliably assertable without the full docx / OTP pipeline: request validation, auth, list
 * happy-paths, and graceful not-found handling.
 */
/**
 * listTemplates() asks whether the caller may approve a template version, and Spatie throws when
 * the permission was never registered at all. RolePermissionSetting owns the real catalogue, so
 * the names come from it - only the documents group is seeded, since running the whole seeder
 * costs 14s per test. Nothing is granted to anyone, so the answer stays false.
 */
function seedDocumentPermissions(): void
{
    $seeder = new RolePermissionSetting;

    Closure::bind(function () {
        foreach ($this->documentPermission() as $permission) {
            Permission::firstOrCreate([
                'name' => $permission['name'],
                'guard_name' => 'sanctum',
            ], [
                'group' => $permission['group'],
            ]);
        }
    }, $seeder, RolePermissionSetting::class)();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

function actAsEmployeeUser(): User
{
    $employee = Employee::factory()->withUser()->create();
    $user = User::where('employee_id', $employee->id)->first();
    actingAs($user);

    return $user;
}

function writeSignableDocxFixture(string $relativePath): void
{
    $absolute = storage_path('app/public/'.$relativePath);
    @mkdir(dirname($absolute), 0775, true);

    $phpWord = new PhpWord;
    $section = $phpWord->addSection();
    $section->addText('Signature: ${employeeSignature}');
    IOFactory::createWriter($phpWord, 'Word2007')->save($absolute);
}

function writeSignaturePngFixture(string $relativePath): void
{
    $absolute = storage_path('app/public/'.$relativePath);
    @mkdir(dirname($absolute), 0775, true);

    $image = imagecreatetruecolor(4, 4);
    imagepng($image, $absolute);
    imagedestroy($image);
}

$base = '/api/signatures';

// ---------------------------------------------------------------------------
// Auth
// ---------------------------------------------------------------------------
it('requires authentication for the signatures endpoints', function () use ($base) {
    $this->getJson($base.'/document-types')->assertStatus(401);
});

// ---------------------------------------------------------------------------
// Validation (invalid/empty payload -> 422) for every DTO-backed endpoint
// ---------------------------------------------------------------------------
it('validates the payload', function (string $method, string $path) use ($base) {
    actAsEmployeeUser();

    $response = $this->json($method, $base.$path, []);

    $response->assertStatus(422);
})->with([
    'store document type' => ['post', '/document-types'],
    'bulk create document type' => ['post', '/document-types/bulk'],
    'bulk edit document type' => ['put', '/document-types/bulk'],
    'bulk delete document type' => ['delete', '/document-types/bulk'],
    'update document type' => ['put', '/document-types/some-uid'],
    'detect placeholder' => ['post', '/document-types/detect-placeholder'],
    'create template' => ['post', '/templates'],
    'approval master document' => ['post', '/templates/some-uid/approval'],
    'generate document' => ['post', '/templates/some-uid/generate'],
    'bulk generate document' => ['post', '/documents/disburse'],
    'bulk delete generated document' => ['delete', '/documents/bulk'],
    'assign signatories' => ['post', '/signatories/assign/some-uid'],
    'store employee signature' => ['post', '/my-signatures'],
    'validate otp' => ['post', '/sign/otp/some-uid/validate'],
]);

// ---------------------------------------------------------------------------
// List endpoints succeed (empty data is fine)
// ---------------------------------------------------------------------------
it('lists resources successfully', function (string $path) use ($base) {
    seedDocumentPermissions();

    actAsEmployeeUser();

    $this->getJson($base.$path)->assertStatus(201);
})->with([
    'document types' => ['/document-types'],
    'templates' => ['/templates'],
    'signatories' => ['/signatories'],
    'my signatures' => ['/my-signatures'],
    'my documents' => ['/documents/mine'],
]);

it('forbids the privileged document list for a non-privileged user', function () use ($base) {
    actAsEmployeeUser();

    $this->getJson($base.'/documents')->assertStatus(403);
});

// ---------------------------------------------------------------------------
// Not-found / graceful error for uid-based endpoints (unknown uid -> 400)
// ---------------------------------------------------------------------------
it('returns a handled error for an unknown uid', function (string $method, string $path) use ($base) {
    actAsEmployeeUser();

    $response = $this->json($method, $base.$path);

    $response->assertStatus(400);
})->with([
    'document sign detail' => ['get', '/documents/unknown-uid'],
    'delete template' => ['delete', '/templates/unknown-uid'],
    'generate sign otp' => ['get', '/sign/otp/unknown-uid'],
    'apply signature' => ['post', '/sign/unknown-doc/apply/unknown-sig'],
    'update applied signature' => ['patch', '/sign/unknown-doc/apply/unknown-sig'],
    'set active signature' => ['patch', '/my-signatures/unknown-uid/activate'],
    'delete signature' => ['delete', '/my-signatures/unknown-uid'],
]);

// ---------------------------------------------------------------------------
// Bulk disburse: audience selection validation and graceful template errors
// ---------------------------------------------------------------------------
it('rejects an unknown disburse target', function () use ($base) {
    actAsEmployeeUser();

    $this->postJson($base.'/documents/disburse', [
        'assign_to' => 'everyone',
    ])->assertStatus(422);
});

it('requires a division id when disbursing to a division', function () use ($base) {
    actAsEmployeeUser();

    $this->postJson($base.'/documents/disburse', [
        'assign_to' => 'division',
    ])->assertStatus(422);
});

it('requires a position id when disbursing to a position', function () use ($base) {
    actAsEmployeeUser();

    $this->postJson($base.'/documents/disburse', [
        'assign_to' => 'position',
    ])->assertStatus(422);
});

it('returns a handled error when disbursing from an unknown template', function () use ($base) {
    actAsEmployeeUser();

    $this->postJson($base.'/documents/disburse', [
        'assign_to' => 'all',
        'template_uid' => 'unknown-template',
    ])->assertStatus(400);
});

// ---------------------------------------------------------------------------
// Delete signature: the "already applied" guard must ignore soft-deleted documents
// ---------------------------------------------------------------------------
it('deletes a signature whose only applied task belongs to a soft-deleted document', function () use ($base) {
    $user = actAsEmployeeUser();

    $signature = EmployeeSignature::factory()->create(['employee_id' => $user->employee_id]);

    $document = EmployeeDocument::factory()->create(['employee_id' => $user->employee_id]);
    EmployeeSignatureTask::factory()->signed()->create([
        'employee_id' => $user->employee_id,
        'employee_document_id' => $document->id,
        'employee_signature_id' => $signature->id,
    ]);
    $document->delete();

    $this->deleteJson($base.'/my-signatures/'.$signature->uid)->assertStatus(201);

    expect(EmployeeSignature::find($signature->id))->toBeNull();
});

it('refuses to delete a signature still applied to a live document', function () use ($base) {
    $user = actAsEmployeeUser();

    $signature = EmployeeSignature::factory()->create(['employee_id' => $user->employee_id]);

    $document = EmployeeDocument::factory()->create(['employee_id' => $user->employee_id]);
    EmployeeSignatureTask::factory()->signed()->create([
        'employee_id' => $user->employee_id,
        'employee_document_id' => $document->id,
        'employee_signature_id' => $signature->id,
    ]);

    $this->deleteJson($base.'/my-signatures/'.$signature->uid)->assertStatus(400);

    expect(EmployeeSignature::find($signature->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Completed download: rendered docx is converted to PDF before streaming
// ---------------------------------------------------------------------------
it('streams a completed document as a PDF', function () use ($base) {
    $user = actAsEmployeeUser();

    // Minimal real .docx carrying the signature placeholder, plus a real signature image,
    // so buildRenderableDocument runs for real; only the PDF engine is faked.
    $docxPath = 'employees/documents/test_'.uniqid().'.docx';
    $signPath = 'signatures/employees/test_'.uniqid().'.png';
    writeSignableDocxFixture($docxPath);
    writeSignaturePngFixture($signPath);

    $signature = EmployeeSignature::factory()->create([
        'employee_id' => $user->employee_id,
        'sign_path' => $signPath,
    ]);

    $document = EmployeeDocument::factory()->create([
        'employee_id' => $user->employee_id,
        'status' => Status::Completed,
        'total_signer' => 1,
        'document_path' => $docxPath,
    ]);
    EmployeeSignatureTask::factory()->signed()->create([
        'employee_id' => $user->employee_id,
        'employee_document_id' => $document->id,
        'employee_signature_id' => $signature->id,
        'order' => 1,
    ]);

    // Fake converter: writes a sibling .pdf and returns its absolute path (no LibreOffice needed).
    app()->bind(DocumentPdfConverter::class, fn () => new class implements DocumentPdfConverter
    {
        public function toPdf(string $absoluteDocxPath): string
        {
            $pdf = preg_replace('/\.docx$/i', '.pdf', $absoluteDocxPath);
            file_put_contents($pdf, '%PDF-1.4 fake');

            return $pdf;
        }
    });

    $response = $this->get($base.'/file/employee/'.$document->uid.'/download');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('document.pdf');

    Storage::disk('public')->delete([$docxPath, $signPath]);
});

// ---------------------------------------------------------------------------
// Document type deletion: the type's own default signers go with it
// ---------------------------------------------------------------------------
it('deletes a document type together with its default signers', function () use ($base) {
    actAsEmployeeUser();

    $type = templateDocumentType();
    $division = DivisionBackup::create(['name' => 'Legal '.fake()->unique()->numerify('###')]);
    $signer = DocumentTypeSigner::create([
        'type_id' => $type->id,
        'division_id' => $division->id,
        'order' => 1,
    ]);

    $this->deleteJson($base.'/document-types/bulk', ['uids' => [$type->id]])->assertStatus(201);

    expect(DocumentType::find($type->id))->toBeNull()
        ->and(DocumentTypeSigner::find($signer->id))->toBeNull();
});

it('keeps a document type and its signers when a template still uses it', function () use ($base) {
    actAsEmployeeUser();

    $type = templateDocumentType();
    $division = DivisionBackup::create(['name' => 'Legal '.fake()->unique()->numerify('###')]);
    $signer = DocumentTypeSigner::create([
        'type_id' => $type->id,
        'division_id' => $division->id,
        'order' => 1,
    ]);
    MasterDocument::create([
        'name' => 'Employment Contract',
        'document_type_id' => $type->id,
    ]);

    $this->deleteJson($base.'/document-types/bulk', ['uids' => [$type->id]])->assertStatus(400);

    // The signer delete runs before the type delete, so the refusal has to roll it back too.
    expect(DocumentType::find($type->id))->not->toBeNull()
        ->and(DocumentTypeSigner::find($signer->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Template creation: the new version lands in review and the approvers hear about it
// ---------------------------------------------------------------------------
function templateDocumentType(): DocumentType
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

it('queues the approval notification when a template version is submitted', function () use ($base) {
    Bus::fake();
    Storage::fake(config('filesystems.default'));
    actAsEmployeeUser();

    $documentType = templateDocumentType();

    $this->postJson($base.'/templates', [
        'name' => 'Employment Contract',
        'document_type_id' => (string) $documentType->id,
        'file' => UploadedFile::fake()->create('contract.docx', 10),
        'placeholders' => ['employeeName'],
    ])->assertStatus(201);

    $version = MasterDocumentFile::query()->latest('id')->first();
    expect($version->status)->toBe(DocumentFileStatus::PendingReview);

    Bus::assertDispatched(
        NotifyGeneratedDocumentJob::class,
        fn (NotifyGeneratedDocumentJob $job) => (new ReflectionProperty($job, 'document'))
            ->getValue($job)
            ->id === $version->master_document_id
    );
});

it('notifies nobody when the document type already has a version under review', function () use ($base) {
    Bus::fake();
    Storage::fake(config('filesystems.default'));
    $user = actAsEmployeeUser();

    $documentType = templateDocumentType();
    $document = MasterDocument::create([
        'name' => 'Employment Contract',
        'document_type_id' => $documentType->id,
    ]);
    MasterDocumentFile::create([
        'master_document_id' => $document->id,
        'path' => 'templates/'.uniqid().'.docx',
        'file_type' => 'docx',
        'status' => DocumentFileStatus::PendingReview,
        'created_by' => $user->id,
    ]);

    $this->postJson($base.'/templates', [
        'name' => 'Employment Contract',
        'document_type_id' => (string) $documentType->id,
        'file' => UploadedFile::fake()->create('contract.docx', 10),
        'placeholders' => ['employeeName'],
    ])->assertStatus(400);

    Bus::assertNotDispatched(NotifyGeneratedDocumentJob::class);
});

// ---------------------------------------------------------------------------
// Template deletion: every version goes, on disk and in the database
// ---------------------------------------------------------------------------
it('deletes a template with all of its versions, signers and stored files', function () use ($base) {
    Storage::fake(config('filesystems.default'));
    actAsEmployeeUser();

    $document = MasterDocument::create([
        'name' => 'Employment Contract',
        'document_type_id' => templateDocumentType()->id,
    ]);

    $paths = collect([DocumentFileStatus::Archived, DocumentFileStatus::Active])
        ->map(function (DocumentFileStatus $status) use ($document) {
            $path = config('signature.master_path').'/'.uniqid().'.docx';
            Storage::put($path, 'docx');

            $version = MasterDocumentFile::create([
                'master_document_id' => $document->id,
                'path' => $path,
                'file_type' => 'docx',
                'status' => $status,
            ]);

            MasterDocumentSigner::create([
                'master_document_id' => $document->id,
                'file_id' => $version->id,
                'order' => 1,
            ]);

            return $path;
        });

    $this->deleteJson($base.'/templates/'.$document->uid)->assertStatus(201);

    expect(MasterDocument::find($document->id))->toBeNull()
        ->and(MasterDocumentFile::where('master_document_id', $document->id)->count())->toBe(0)
        ->and(MasterDocumentSigner::where('master_document_id', $document->id)->count())->toBe(0);

    $paths->each(fn (string $path) => expect(Storage::exists($path))->toBeFalse());
});

it('deletes a template whose stored file is already gone from disk', function () use ($base) {
    Storage::fake(config('filesystems.default'));
    actAsEmployeeUser();

    $document = MasterDocument::create([
        'name' => 'Employment Contract',
        'document_type_id' => templateDocumentType()->id,
    ]);
    MasterDocumentFile::create([
        'master_document_id' => $document->id,
        'path' => config('signature.master_path').'/missing.docx',
        'file_type' => 'docx',
        'status' => DocumentFileStatus::Active,
    ]);

    $this->deleteJson($base.'/templates/'.$document->uid)->assertStatus(201);

    expect(MasterDocument::find($document->id))->toBeNull();
});

// ---------------------------------------------------------------------------
// Template approval: the decision is persisted and the creator is notified
// ---------------------------------------------------------------------------
function createPendingTemplate(User $creator): MasterDocument
{
    $document = MasterDocument::create(['name' => 'Employment Contract']);

    MasterDocumentFile::create([
        'master_document_id' => $document->id,
        'path' => 'templates/'.uniqid().'.docx',
        'file_type' => 'docx',
        'status' => DocumentFileStatus::PendingReview,
        'created_by' => $creator->id,
    ]);

    return $document;
}

it('activates the pending version and notifies its creator on approval', function () use ($base) {
    Bus::fake();
    $user = actAsEmployeeUser();
    $document = createPendingTemplate($user);

    $this->postJson($base.'/templates/'.$document->uid.'/approval', [
        'status' => '1',
    ])->assertStatus(201);

    $version = MasterDocumentFile::where('master_document_id', $document->id)->first();
    expect($version->status)->toBe(DocumentFileStatus::Active)
        ->and($version->approved_by)->toBe($user->id)
        ->and($version->rejected_by)->toBeNull();

    Bus::assertDispatched(
        NotifyApprovalDocumentJob::class,
        fn (NotifyApprovalDocumentJob $job) => (new ReflectionProperty($job, 'version'))
            ->getValue($job)
            ->status === DocumentFileStatus::Active
    );
});

it('rejects the pending version with its reason and notifies its creator', function () use ($base) {
    Bus::fake();
    $user = actAsEmployeeUser();
    $document = createPendingTemplate($user);

    $this->postJson($base.'/templates/'.$document->uid.'/approval', [
        'status' => '0',
        'reason' => 'Wrong signature placement',
    ])->assertStatus(201);

    $version = MasterDocumentFile::where('master_document_id', $document->id)->first();
    expect($version->status)->toBe(DocumentFileStatus::Rejected)
        ->and($version->rejected_by)->toBe($user->id)
        ->and($version->approval_note)->toBe('Wrong signature placement');

    // The job must receive the refreshed version, otherwise it would announce an approval.
    Bus::assertDispatched(NotifyApprovalDocumentJob::class, function (NotifyApprovalDocumentJob $job) {
        $version = (new ReflectionProperty($job, 'version'))->getValue($job);

        return $version->status === DocumentFileStatus::Rejected
            && $version->approval_note === 'Wrong signature placement';
    });
});

it('returns a handled error and notifies nobody when a template has no pending version', function () use ($base) {
    Bus::fake();
    actAsEmployeeUser();

    $document = MasterDocument::create(['name' => 'Employment Contract']);

    $this->postJson($base.'/templates/'.$document->uid.'/approval', [
        'status' => '1',
    ])->assertStatus(400);

    Bus::assertNotDispatched(NotifyApprovalDocumentJob::class);
});

// ---------------------------------------------------------------------------
// File render endpoints: unknown uid returns a JSON error (not a file)
// ---------------------------------------------------------------------------
it('returns a JSON error (not a file) when rendering an unknown document', function (string $path) use ($base) {
    actAsEmployeeUser();

    $this->getJson($base.$path)->assertStatus(400);
})->with([
    'render employee document' => ['/file/employee/unknown-uid/render'],
    'download completed document' => ['/file/employee/unknown-uid/download'],
    'render template document' => ['/file/render/unknown-template/1'],
]);
