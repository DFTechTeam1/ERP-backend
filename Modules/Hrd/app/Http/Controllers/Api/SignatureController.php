<?php

namespace Modules\Hrd\Http\Controllers\Api;

use App\Data\Hrd\Signature\ApprovalDocumentData;
use App\Data\Hrd\Signature\AssignSignatoriesData;
use App\Data\Hrd\Signature\BulkCreateDocumentTypeData;
use App\Data\Hrd\Signature\BulkDeleteDocumentTypeData;
use App\Data\Hrd\Signature\BulkDeleteGeneratedDocumentData;
use App\Data\Hrd\Signature\BulkGenerateDocumentData;
use App\Data\Hrd\Signature\BulkUpdateDocumentTypeData;
use App\Data\Hrd\Signature\CreateDocumentTypeData;
use App\Data\Hrd\Signature\CreateTemplateData;
use App\Data\Hrd\Signature\DetectPlaceholderData;
use App\Data\Hrd\Signature\GenerateDocumentData;
use App\Data\Hrd\Signature\StoreEmployeeSignatureData;
use App\Data\Hrd\Signature\UpdateDocumentTypeData;
use App\Data\Hrd\Signature\ValidateOtpData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Hrd\Services\SignatureService;
use Storage;

class SignatureController extends Controller
{
    public function __construct(
        private readonly SignatureService $service
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('hrd::index');
    }

    /**
     * List all document types with their default signer configuration (paginated).
     */
    public function listDocumentTypes(): JsonResponse
    {
        return apiResponse($this->service->listDocumentTypes());
    }

    /**
     * Create a single document type together with its default signers.
     *
     * @param  CreateDocumentTypeData  $payload  Validated document type payload
     */
    public function storeDocumentTypes(CreateDocumentTypeData $payload): JsonResponse
    {
        return apiResponse($this->service->storeDocumentTypes($payload));
    }

    /**
     * Create multiple document types in one request.
     *
     * @param  BulkCreateDocumentTypeData  $request  Collection of document types to create
     */
    public function bulkCreateDocumentType(BulkCreateDocumentTypeData $request): JsonResponse
    {
        return apiResponse($this->service->bulkCreateDocumentType($request));
    }

    /**
     * Delete multiple document types in one request.
     *
     * @param  BulkDeleteDocumentTypeData  $request  Ids of the document types to delete
     */
    public function bulkDeleteDocumentType(BulkDeleteDocumentTypeData $request): JsonResponse
    {
        return apiResponse($this->service->bulkDeleteDocumentType($request));
    }

    /**
     * Update a single document type by its id.
     *
     * @param  UpdateDocumentTypeData  $request  Validated document type payload
     * @param  string  $documentId  Document type id to update
     */
    public function updateDocumentType(UpdateDocumentTypeData $request, string $documentId): JsonResponse
    {
        return apiResponse($this->service->updateDocumentType($request, $documentId));
    }

    /**
     * Update multiple document types in one request.
     *
     * @param  BulkUpdateDocumentTypeData  $request  Collection of document types to update
     */
    public function bulkEditDocumentType(BulkUpdateDocumentTypeData $request): JsonResponse
    {
        return apiResponse($this->service->bulkEditDocumentType($request));
    }

    /**
     * List documents that have already been generated from templates.
     */
    public function generatedDocumentList(): JsonResponse
    {
        return apiResponse($this->service->generatedDocumentList());
    }

    /**
     * List generated documents the authenticated user is a signer on (non-privileged users).
     */
    public function myGeneratedDocumentList(): JsonResponse
    {
        return apiResponse($this->service->myGeneratedDocumentList());
    }

    /**
     * Stream a generated employee document as a downloadable .docx file.
     *
     * Signatures are composited on the fly. Pass `?preview=1` to stream the document with no
     * signatures overlaid (used to preview before signing). The rendered file is temporary and
     * is deleted once streamed.
     *
     * @param  Request  $request  Incoming HTTP request (reads the `preview` flag)
     * @param  string  $employeeDocumentUid  Uid of the generated employee document
     * @return Response|JsonResponse
     */
    public function renderEmployeeDocument(Request $request, string $employeeDocumentUid)
    {
        $withSignatures = ! $request->boolean('preview');

        $data = $this->service->renderEmployeeDocument($employeeDocumentUid, $withSignatures);

        if ($data['error']) {
            return apiResponse($data);
        }

        $relativePath = $data['data']['path'];
        $file = file_get_contents(storage_path('app/public/'.$relativePath));
        $filename = 'document';

        if (! empty($data['data']['is_temporary'])) {
            Storage::disk('public')->delete($relativePath);
        }

        return response($file, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.docx"',
        ]);
    }

    /**
     * Stream a completed (fully signed) employee document as a downloadable PDF file.
     *
     * Refuses documents that are not yet completed. The rendered file is temporary and is
     * deleted once streamed.
     *
     * @param  string  $employeeDocumentUid  Uid of the generated employee document
     * @return Response|JsonResponse
     */
    public function downloadCompletedDocument(string $employeeDocumentUid)
    {
        $data = $this->service->downloadCompletedDocument($employeeDocumentUid);

        if ($data['error']) {
            return apiResponse($data);
        }

        $relativePath = $data['data']['path'];
        $file = file_get_contents(storage_path('app/public/'.$relativePath));
        $filename = 'document';
        $mime = $data['data']['mime'] ?? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $extension = $data['data']['extension'] ?? 'docx';

        if (! empty($data['data']['is_temporary'])) {
            Storage::disk('public')->delete($relativePath);
        }

        return response($file, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$filename.'.'.$extension.'"',
        ]);
    }

    /**
     * Stream a specific version of a master template as a downloadable .docx file.
     *
     * @param  string  $templateUid  Uid of the master template
     * @param  string  $versionId  Version id of the template to render
     * @return Response|JsonResponse
     */
    public function renderTemplateDocument(string $templateUid, string $versionId)
    {
        $data = $this->service->renderTemplateDocument($templateUid, $versionId);

        if ($data['error']) {
            return apiResponse($data);
        }

        $file = file_get_contents(storage_path('app/public/'.$data['data']['path']));
        $filename = 'document';

        return response($file, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.docx"',
        ]);
    }

    /**
     * Show a generated document with its signing progress and signer list.
     *
     * @param  string  $documentUid  Uid of the generated document
     */
    public function documentSignDetail(string $documentUid): JsonResponse
    {
        return apiResponse($this->service->documentSignDetail($documentUid));
    }

    /**
     * Soft delete a single generated document (privileged, non-completed only).
     *
     * @param  string  $documentUid  Uid of the generated document to delete
     */
    public function deleteGeneratedDocument(string $documentUid): JsonResponse
    {
        return apiResponse($this->service->deleteGeneratedDocument($documentUid));
    }

    /**
     * Soft delete many generated documents at once (privileged, non-completed only).
     *
     * @param  BulkDeleteGeneratedDocumentData  $payload  Uids of the generated documents to delete
     */
    public function bulkDeleteGeneratedDocument(BulkDeleteGeneratedDocumentData $payload): JsonResponse
    {
        return apiResponse($this->service->bulkDeleteGeneratedDocument($payload));
    }

    /**
     * Verify the signing OTP for the authenticated signer and mark their task as signed.
     *
     * @param  ValidateOtpData  $request  Validated 6-digit OTP payload
     * @param  string  $employeeDocumentUid  Uid of the employee document being signed
     */
    public function validateOtp(ValidateOtpData $request, string $employeeDocumentUid): JsonResponse
    {
        return apiResponse($this->service->validateOtp($employeeDocumentUid, $request->otp));
    }

    /**
     * Generate and email a one-time password the signer uses to sign the document.
     *
     * @param  string  $employeeDocumentUid  Uid of the employee document to sign
     */
    public function generateSignOtp(string $employeeDocumentUid): JsonResponse
    {
        return apiResponse($this->service->generateSignOtp($employeeDocumentUid));
    }

    /**
     * Reject / Approve master document template.
     *
     * @param  ApprovalDocumentData  $request  Approval decision (status + reason)
     * @param  string  $documentUid  Uid of the master document template
     */
    public function approvalMasterDocument(ApprovalDocumentData $request, string $documentUid): JsonResponse
    {
        return apiResponse($this->service->approvalMasterDocument($request, $documentUid));
    }

    /**
     * Detect placeholders in an uploaded document and the available replacement variables.
     *
     * @param  DetectPlaceholderData  $request  Uploaded file and document type id
     */
    public function detectPlaceholder(DetectPlaceholderData $request): JsonResponse
    {
        return apiResponse($this->service->detectPlaceholder($request));
    }

    /**
     * Create a master document template.
     *
     * @param  CreateTemplateData  $request  Template name, document type, file and placeholders
     */
    public function createTemplate(CreateTemplateData $request): JsonResponse
    {
        return apiResponse($this->service->createTemplate($request));
    }

    /**
     * List available master template documents.
     */
    public function listTemplates(): JsonResponse
    {
        return apiResponse($this->service->listTemplates());
    }

    /**
     * Assign employees to the signatories of a document mapping.
     *
     * @param  AssignSignatoriesData  $request  Signatory assignment payload
     * @param  string  $mappingUid  Uid of the signatories mapping
     */
    public function assignSignatories(AssignSignatoriesData $request, string $mappingUid): JsonResponse
    {
        return apiResponse($this->service->assignSignatories($request, $mappingUid));
    }

    /**
     * Delete a master template document.
     *
     * @param  string  $templateUid  Uid of the template to delete
     */
    public function deleteTemplate(string $templateUid): JsonResponse
    {
        return apiResponse($this->service->deleteTemplate($templateUid));
    }

    /**
     * List the signatories (people who can be assigned to sign documents).
     */
    public function listSignatories(): JsonResponse
    {
        return apiResponse($this->service->listSignatories());
    }

    /**
     * Apply the authenticated employee's saved signature onto a generated document.
     *
     * @param  string  $employeeDocumentUid  Uid of the employee document being signed
     * @param  string  $signatureUid  Uid of the employee's saved signature to apply
     */
    public function applySignatureToDocument(string $employeeDocumentUid, string $signatureUid): JsonResponse
    {
        return apiResponse($this->service->applySignatureToDocument($signatureUid, $employeeDocumentUid));
    }

    /**
     * Replace the signature the authenticated employee already applied to a document.
     *
     * @param  string  $employeeDocumentUid  Uid of the employee document
     * @param  string  $signatureUid  Uid of the replacement signature
     */
    public function updateAppliedSignature(string $employeeDocumentUid, string $signatureUid): JsonResponse
    {
        return apiResponse($this->service->updateAppliedSignature($signatureUid, $employeeDocumentUid));
    }

    /**
     * List the authenticated employee's saved signatures.
     */
    public function listEmployeeSignatures(): JsonResponse
    {
        return apiResponse($this->service->listEmployeeSignatures());
    }

    /**
     * Upload a new signature image for the authenticated employee and mark it active.
     *
     * @param  StoreEmployeeSignatureData  $request  Uploaded signature image
     */
    public function storeEmployeeSignature(StoreEmployeeSignatureData $request): JsonResponse
    {
        return apiResponse($this->service->storeEmployeeSignature($request));
    }

    /**
     * Mark one of the authenticated employee's signatures active, deactivating the rest.
     *
     * @param  string  $signatureUid  Uid of the signature to activate
     */
    public function setActiveEmployeeSignature(string $signatureUid): JsonResponse
    {
        return apiResponse($this->service->setActiveEmployeeSignature($signatureUid));
    }

    /**
     * Delete one of the authenticated employee's signatures.
     *
     * @param  string  $signatureUid  Uid of the signature to delete
     */
    public function deleteEmployeeSignature(string $signatureUid): JsonResponse
    {
        return apiResponse($this->service->deleteEmployeeSignature($signatureUid));
    }

    /**
     * Generate a signable document for an employee from a master template.
     *
     * @param  GenerateDocumentData  $payload  Employee id and version label
     * @param  string  $templateUid  Uid of the master template to generate from
     * @return JsonResponse
     */
    public function generateDocument(GenerateDocumentData $payload, string $templateUid)
    {
        return apiResponse($this->service->generateDocument($payload, $templateUid));
    }

    /**
     * Disburse a signable document from a master template to a whole audience of employees:
     * every active employee, everyone in a division, or everyone holding a position.
     *
     * @param  BulkGenerateDocumentData  $payload  Audience selection plus the source template
     */
    public function bulkGenerateDocument(BulkGenerateDocumentData $payload): JsonResponse
    {
        return apiResponse($this->service->bulkGenerateDocument($payload));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('hrd::create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request  Incoming HTTP request
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     *
     * @param  mixed  $id  Identifier of the resource
     */
    public function show($id)
    {
        return view('hrd::show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  mixed  $id  Identifier of the resource
     */
    public function edit($id)
    {
        return view('hrd::edit');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request  Incoming HTTP request
     * @param  mixed  $id  Identifier of the resource
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  mixed  $id  Identifier of the resource
     */
    public function destroy($id)
    {
        //
    }
}
