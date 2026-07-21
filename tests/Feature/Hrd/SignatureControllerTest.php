<?php

use App\Models\User;
use Modules\Hrd\Models\Employee;

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
