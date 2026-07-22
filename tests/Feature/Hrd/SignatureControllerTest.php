<?php

use App\Enums\Hrd\Signature\Template\Status;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Modules\Hrd\Contracts\DocumentPdfConverter;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeeDocument;
use Modules\Hrd\Models\EmployeeSignature;
use Modules\Hrd\Models\EmployeeSignatureTask;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

use function Pest\Laravel\actingAs;

/**
 * The signature endpoints all live under the auth.session-protected /api/signatures group and
 * delegate to SignatureService. This suite covers each controller method at the level that is
 * reliably assertable without the full docx / OTP pipeline: request validation, auth, list
 * happy-paths, and graceful not-found handling.
 */
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
