<?php

namespace Modules\Hrd\Services;

use App\Data\Hrd\Signature\ApprovalDocumentData;
use App\Data\Hrd\Signature\AssignSignatoriesData;
use App\Data\Hrd\Signature\BulkCreateDocumentTypeData;
use App\Data\Hrd\Signature\BulkDeleteDocumentTypeData;
use App\Data\Hrd\Signature\BulkDeleteGeneratedDocumentData;
use App\Data\Hrd\Signature\BulkGenerateDocumentData;
use App\Data\Hrd\Signature\BulkUpdateDocumentTypeData;
use App\Data\Hrd\Signature\CreateDocumentTypeData;
use App\Data\Hrd\Signature\CreateTemplateData;
use App\Data\Hrd\Signature\DefaultSignerItemData;
use App\Data\Hrd\Signature\DetailDocumentSignData;
use App\Data\Hrd\Signature\DetectPlaceholderData;
use App\Data\Hrd\Signature\EmployeeSignatureData;
use App\Data\Hrd\Signature\GeneratedDocumentListData;
use App\Data\Hrd\Signature\GenerateDocumentData;
use App\Data\Hrd\Signature\ListDocumentTypeData;
use App\Data\Hrd\Signature\MyDocumentSignerData;
use App\Data\Hrd\Signature\MyGeneratedDocumentListData;
use App\Data\Hrd\Signature\OrgSignatoriesListData;
use App\Data\Hrd\Signature\OutputListDocumentTypeData;
use App\Data\Hrd\Signature\SelectedOrgSignatureSignerData;
use App\Data\Hrd\Signature\SignatoriesDivisionPicData;
use App\Data\Hrd\Signature\SignatoriesListData;
use App\Data\Hrd\Signature\StoreEmployeeSignatureData;
use App\Data\Hrd\Signature\TemplateListData;
use App\Data\Hrd\Signature\UpdateDocumentTypeData;
use App\Data\Hrd\Signatured\DocumentVersionListData;
use App\Data\Hrd\Signer\DetailDocumentSignEmployeeData;
use App\Data\Hrd\Signer\DetailDocumentSignSignersData;
use App\Enums\Hrd\Signature\GenerateDocument\AssignTo;
use App\Enums\Hrd\Signature\SignatureTaskStatus;
use App\Enums\Hrd\Signature\Template\DocumentFileStatus;
use App\Enums\Hrd\Signature\Template\Status;
use App\Enums\System\BaseRole;
use App\Exceptions\DataNotFound;
use App\Exceptions\DetectPlaceholderFailed;
use App\Exceptions\FailedToUploadFile;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Company\Repository\DivisionRepository;
use Modules\Company\Repository\PositionRepository;
use Modules\Hrd\Contracts\DocumentPdfConverter;
use Modules\Hrd\Exceptions\CompletedDocumentNotDeletable;
use Modules\Hrd\Exceptions\DocumentNotCompleted;
use Modules\Hrd\Exceptions\DocumentTypeInUse;
use Modules\Hrd\Exceptions\PdfConversionFailed;
use Modules\Hrd\Exceptions\SignatureNotEditable;
use Modules\Hrd\Exceptions\TemplateStillHavePendingReview;
use Modules\Hrd\Exceptions\UserAlreadySigned;
use Modules\Hrd\Exceptions\UserNotHaveAccessToSign;
use Modules\Hrd\Jobs\DocumentCompletedNotificationJob;
use Modules\Hrd\Jobs\DocumentDeletedNotificationJob;
use Modules\Hrd\Jobs\GenerateDocumentNotificationJob;
use Modules\Hrd\Jobs\SendOtpSignJob;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeeDocument;
use Modules\Hrd\Models\EmployeeSignature;
use Modules\Hrd\Models\EmployeeSignatureTask;
use Modules\Hrd\Models\MasterDocument;
use Modules\Hrd\Repository\DocumentTypeRepository;
use Modules\Hrd\Repository\EmployeeDocumentRepository;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Hrd\Repository\EmployeeSignatureRepository;
use Modules\Hrd\Repository\EmployeeSignatureTaskRepository;
use Modules\Hrd\Repository\MasterDocumentFileRepository;
use Modules\Hrd\Repository\MasterDocumentRepository;
use Modules\Hrd\Repository\SignatoriesMappingRepository;
use PhpOffice\PhpWord\TemplateProcessor;

class SignatureService
{
    public function __construct(
        private readonly DocumentTypeRepository $documentTypeRepo,
        private readonly PositionRepository $positionRepo,
        private readonly DivisionRepository $divisionRepo,
        private readonly MasterDocumentRepository $masterDocumentRepo,
        private readonly MasterDocumentFileRepository $masterFileRepo,
        private readonly EmployeeRepository $employeeRepo,
        private readonly SignatoriesMappingRepository $signatoriesMappingRepo,
        private readonly EmployeeDocumentRepository $employeeDocumentRepo,
        private readonly EmployeeSignatureTaskRepository $signatureTaskRepo,
        private readonly EmployeeSignatureRepository $employeeSignatureRepo,
        private readonly DocumentPdfConverter $pdfConverter
    ) {}

    /**
     * Normalize a document type payload into the columns/relations the repository expects.
     *
     * @param  CreateDocumentTypeData|UpdateDocumentTypeData  $payload  Incoming document type data
     * @return array<string, mixed> Parsed attributes including the resolved `signers` list
     */
    protected function parseDocumentTypePayload(CreateDocumentTypeData|UpdateDocumentTypeData $payload): array
    {
        $formattedDivisions = [];
        foreach ($payload->default_signers as $signer) {
            $division = $this->divisionRepo->show(uid: $signer->division_id, select: 'id');
            $formattedDivisions[] = [
                'division_id' => $division->id,
                'order' => $signer->order,
            ];
        }

        return [
            'category' => $payload->category,
            'name' => $payload->name,
            'code' => $payload->code,
            'retention' => $payload->retention_years,
            'default_number_of_signers' => count($payload->default_signers),
            'status' => $payload->is_active ?? true,
            'signers' => $formattedDivisions,
        ];
    }

    /**
     * Fetch a paginated list of document types with their default signers.
     *
     * @return array Response envelope with `OutputListDocumentTypeData` payload
     */
    public function listDocumentTypes(): array
    {
        try {
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;

            $hrdPosition = $this->positionRepo->show(uid: '', select: 'id,name', where: "name = 'hrd'");

            $data = $this->documentTypeRepo->get([
                'select' => ['id', 'code', 'name', 'retention', 'default_number_of_signers', 'status', 'category'],
                'skip' => $page,
                'take' => $itemsPerPage,
                'with' => [
                    'signers:id,type_id,division_id,order',
                    'signers.division:id,uid,name',
                ],
            ])->map(function ($item) {
                $divisions = [];
                foreach ($item->signers as $signer) {
                    $divisions[] = new DefaultSignerItemData(
                        division_id: $signer->division->uid,
                        order: $signer->order,
                        name: $signer->division->name,
                        signer_id: $signer->id
                    );
                }

                return new ListDocumentTypeData(
                    name: $item->name,
                    uid: (string) $item->id,
                    code: $item->code,
                    category: $item->category->label(),
                    category_color: $item->category->color(),
                    retention_years: $item->retention,
                    default_signers: $item->default_number_of_signers,
                    is_have_active_template: true,
                    is_active: (bool) $item->status,
                    default_signer_items: $divisions
                );
            })->all();

            $totalData = $this->documentTypeRepo->get([
                'select' => 'id',
            ])->count();

            $output = new OutputListDocumentTypeData(
                paginated: $data,
                totalData: $totalData
            );

            return generalResponse(message: 'Success', data: OutputListDocumentTypeData::from($output)->toArray());
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Create a single document type and assign its default signers (transactional).
     *
     * @param  CreateDocumentTypeData  $payload  Validated document type data
     * @return array Response envelope with a success/error message
     */
    public function storeDocumentTypes(CreateDocumentTypeData $payload): array
    {
        DB::beginTransaction();
        try {
            $parse = $this->parseDocumentTypePayload($payload);
            $type = $this->documentTypeRepo->store(
                collect($parse)
                    ->except(['signers'])
                    ->merge(['created_by' => Auth::id()])
                    ->toArray()
            );

            $this->documentTypeRepo->assignSigners($parse['signers'], $type);

            DB::commit();

            return generalResponse(message: __('notification.successCreateDocumentType'));
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Create multiple document types in a single transaction.
     *
     * @param  BulkCreateDocumentTypeData  $payload  Collection of document types to create
     * @return array Response envelope with a success/error message
     */
    public function bulkCreateDocumentType(BulkCreateDocumentTypeData $payload): array
    {
        DB::beginTransaction();

        try {
            $actorId = Auth::id();

            $this->documentTypeRepo->bulkCreate(
                data: collect(BulkCreateDocumentTypeData::from($payload)->types)
                    ->map(function ($item) use ($actorId) {
                        return collect($this->parseDocumentTypePayload($item))
                            ->merge(['created_by' => $actorId])
                            ->toArray();
                    })->toArray()
            );

            DB::commit();

            return generalResponse(message: __('notification.successCreateDocumentType'));
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Bulk delete document types, refusing any that are still referenced by a template.
     *
     * @param  BulkDeleteDocumentTypeData  $uids  Ids of the document types to delete
     * @return array Response envelope with a success/error message
     *
     * @throws DocumentTypeInUse
     */
    public function bulkDeleteDocumentType(BulkDeleteDocumentTypeData $uids): array
    {
        try {
            // Validation each uid. Check to document template relation
            $types = $this->documentTypeRepo->get([
                'whereIn' => ['id' => $uids->uids],
                'select' => ['id'],
                'with' => ['masterDocuments:id,document_type_id'],
            ]);

            if ($types->contains(fn ($type) => $type->masterDocuments->isNotEmpty())) {
                throw new DocumentTypeInUse;
            }

            $this->documentTypeRepo->bulkDelete($uids->uids);

            return generalResponse(message: __('notification.successDeleteDocumentType'));
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update a document type and re-sync its signers (transactional).
     *
     * @param  \App\Data\hrd\Signature\UpdateDocumentTypeData  $request  Validated document type data
     * @param  string  $documentId  Id of the document type to update
     * @return array Response envelope with a success/error message
     *
     * @throws DataNotFound
     */
    public function updateDocumentType(\App\Data\hrd\Signature\UpdateDocumentTypeData $request, string $documentId): array
    {
        DB::beginTransaction();
        try {
            $documentType = $this->documentTypeRepo->show([
                'where' => ['id' => $documentId],
                'select' => ['id'],
            ]);

            if (! $documentType) {
                throw new DataNotFound(message: __('notification.documentTypeNotFound'));
            }

            $parse = $this->parseDocumentTypePayload($request);
            $this->documentTypeRepo->update($documentType, $parse);

            $this->documentTypeRepo->assignSigners($parse['signers'], $documentType);

            DB::commit();

            return generalResponse(
                message: __('notification.successUpdateDocumentType'),
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Apply a shared set of changes (category, active flag, retention) to many document types.
     *
     * @param  BulkUpdateDocumentTypeData  $payload  Target ids plus the fields to change
     * @return array Response envelope with a success/error message
     */
    public function bulkEditDocumentType(BulkUpdateDocumentTypeData $payload): array
    {
        DB::beginTransaction();
        try {
            // mapping payload
            $payloadUpdate = [];
            if ($payload->changes->category) {
                $payloadUpdate['category'] = $payload->changes->category;
            }
            if (gettype($payload->changes->is_active) === 'boolean') {
                $payloadUpdate['status'] = $payload->changes->is_active;
            }
            if ($payload->changes->retention_years) {
                $payloadUpdate['retention'] = $payload->changes->retention_years;
            }

            $this->documentTypeRepo->bulkUpdate($payloadUpdate, $payload->uids);

            DB::commit();

            return generalResponse(
                message: __('notification.successUpdateDocumentType'),
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Ensure a directory exists on the public disk before writing files into it.
     *
     * @param  string  $path  Path relative to the public disk root
     */
    protected function createFolder($path): void
    {
        if (Storage::disk('public')->directoryExists($path)) {
            Storage::disk('public')->makeDirectory($path);
        }
    }

    /**
     * Extract the placeholders found in a Word template and compare them to the system's
     * available replacement columns.
     *
     * @param  string  $filepath  Absolute path to the .docx template
     * @return array{error: bool, data?: array{availables: array<int, string>, variables: array<int, string>, missing: array<int, string>}}
     */
    protected function breakdownPlaceholder(string $filepath): array
    {
        try {
            $templateProcessor = new TemplateProcessor($filepath);
            $variables = $templateProcessor->getVariables();

            $availables = array_keys(config('signature.available_replacer_column'));

            // Missing key
            $missingKeys = [];
            foreach ($variables as $variable) {
                if (! in_array($variable, $availables) && ! Str::contains(strtolower($variable), 'signature')) {
                    $missingKeys[] = $variable;
                }
            }

            return [
                'error' => false,
                'data' => [
                    'availables' => $availables,
                    'variables' => $variables,
                    'missing' => $missingKeys,
                ],
            ];
        } catch (\Throwable $th) {
            return [
                'error' => true,
            ];
        }
    }

    /**
     * Inspect an uploaded document and report its placeholders against the system's variables.
     *
     * Steps performed:
     * - validate the signature placeholders match the document type's signers
     * - detect every placeholder in the document
     * - expose the replacement variables the system can provide
     * - flag missing parameters (placeholders with no matching system variable)
     * - decide whether the user can continue to preview (`can_continue`)
     *
     * @param  DetectPlaceholderData  $payload  Uploaded file and target document type id
     * @return array Response envelope with the detected placeholders and continue flag
     *
     * @throws DetectPlaceholderFailed
     */
    public function detectPlaceholder(DetectPlaceholderData $payload): array
    {
        try {
            $file = $payload->file;

            $type = $this->documentTypeRepo->show([
                'where' => ['id' => $payload->documentTypeId],
                'select' => ['id'],
                'with' => [
                    'signers:id,type_id',
                ],
            ]);

            // upload to tmp folder first
            $this->createFolder('signature/tmp');

            $filename = 'tmp_file'.strtotime('now').rand(100, 999).'.'.$file->getClientOriginalExtension();
            Storage::disk('public')
                ->putFileAs('signature/tmp', $file, $filename);

            $placeholders = $this->breakdownPlaceholder(storage_path('app/public/signature/tmp/'.$filename));

            if ($placeholders['error']) {
                throw new DetectPlaceholderFailed;
            }

            $isSignatureEquals = (bool) $type->signers->count() == count($placeholders['data']['variables']);

            $merged = array_merge($placeholders['data'], ['is_signature_equals' => $isSignatureEquals]);

            $merged['can_continue'] = (bool) count($merged['missing']) == 0 && $isSignatureEquals;

            // Delete tmp file
            Storage::disk('public')->delete('signature/tmp/'.$filename);

            return generalResponse(
                message: 'Success',
                data: $merged
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Create (or version up) a master template: upload the file, record it as pending review,
     * and attach the document type's default signers (transactional).
     *
     * @param  CreateTemplateData  $payload  Template name, document type, file and placeholders
     * @return array Response envelope with a success/error message
     *
     * @throws DataNotFound
     * @throws TemplateStillHavePendingReview
     * @throws FailedToUploadFile
     */
    public function createTemplate(CreateTemplateData $payload): array
    {
        DB::beginTransaction();
        try {
            $documentType = $this->documentTypeRepo->show([
                'where' => ['id' => $payload->document_type_id],
                'select' => ['id', 'code'],
                'with' => [
                    'signers' => function ($query) {
                        $query->select(['id', 'type_id', 'division_id', 'order'])
                            ->orderBy('order', 'asc');
                    },
                ],
            ]);

            if (! $documentType) {
                throw new DataNotFound('Document type not found.');
            }

            // Default signers
            $signers = $documentType->signers->map(function ($signer) {
                return [
                    'order' => $signer->order,
                    'division_id' => $signer->division_id,
                ];
            })->values();

            // Get current template based on document type id.
            $master = $this->masterDocumentRepo->show([
                'where' => ['document_type_id' => $payload->document_type_id],
                'select' => ['id', 'current_active_version_text'],
                'with' => [
                    'files',
                    'signers',
                ],
            ]);

            if (! $master) { // Create if not exists
                $master = $this->masterDocumentRepo->store([
                    'name' => $payload->name,
                    'document_type_id' => $payload->document_type_id,
                ]);
            }

            // Stop if given document type still have pending review template
            if ($master->isHavePendingReview()) {
                throw new TemplateStillHavePendingReview;
            }

            // Upload files
            $this->createFolder(config('signature.master_path'));
            $filename = 'document_master_'.$documentType->code.'_version'.($master->files->count() + 1).'.'.$payload->file->getClientOriginalExtension();
            $storeFile = Storage::putFileAs(config('signature.master_path'), $payload->file, $filename);

            if (! $storeFile) {
                throw new FailedToUploadFile;
            }

            // Record files
            $files = $master->files()->create([
                'path' => config('signature.master_path').'/'.$filename,
                'file_type' => $payload->file->getClientOriginalExtension(),
                'placeholder_mapping' => $payload->placeholders,
                'status' => DocumentFileStatus::PendingReview,
                'created_by' => Auth::id(),
            ]);

            $signers = collect($signers)->map(function ($signer) use ($files) {
                $signer = array_merge($signer, ['file_id' => $files->id]);

                return $signer;
            })->values();

            $master->signers()->createMany($signers);

            DB::commit();

            return generalResponse(
                message: __('notification.successCreateDocumentTemplate'),
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Fetch a paginated list of available master templates.
     *
     * @return array Response envelope with a list of `TemplateListData`
     */
    public function listTemplates(): array
    {
        try {
            /** @var array<int, TemplateListData> */
            $output = [];

            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;

            $data = $this->masterDocumentRepo->get([
                'select' => ['id', 'uid', 'name', 'document_type_id', 'current_active_version_text', 'updated_at'],
                'skip' => $page,
                'take' => $itemsPerPage,
                'with' => [
                    'documentType:id,name,code',
                    'documentType.signers:id,division_id,type_id',
                    'documentType.signers.division:id,name',
                    'files:id,master_document_id,placeholder_mapping,version,status,created_at,path,approval_note',
                    'files.author:id,employee_id,email',
                    'files.author.employee:id,nickname',
                    'activeDocument:id,master_document_id,placeholder_mapping,version,status,created_at',
                    'activeDocument.author:id,employee_id,email',
                    'activeDocument.author.employee:id,nickname',
                ],
            ])->map(function ($item) {
                $versions = [];
                foreach ($item->files as $file) {
                    $versions[] = new DocumentVersionListData(
                        uid: (string) $file->id,
                        label: $item->name,
                        status: $file->status->label(),
                        is_active: $file->status == DocumentFileStatus::Active ? true : false,
                        placeholders: count($file->placeholder_mapping),
                        date: $file->created_at,
                        version_status_color: $file->status->color(),
                        rejected_reason: $file->status == DocumentFileStatus::Rejected ? ($file->approval_note ?? null) : null,
                        is_pending: $file->status === DocumentFileStatus::PendingReview,
                        author: ! $file->author ? 'N/A' : $file->author?->employee?->nickname ?? $file->author->email,
                        file_url: asset('storage/'.$file->path)
                    );
                }

                // Define signer chain
                $chain = [];
                foreach ($item->documentType->signers as $typeSigner) {
                    $chain[] = $typeSigner->division->name;
                }
                $chain = array_merge($chain, ['Employee']);

                return new TemplateListData(
                    uid: $item->uid,
                    name: $item->name,
                    active_document_uid: $item?->activeDocument?->id ?? null,
                    type: $item->documentType->code,
                    latest_version_label: $item->current_active_version_text,
                    updated_at: $item->updated_at,
                    active_version_label: $item->activeDocument ? $item->current_active_version_text : '',
                    active_version_status: $item->activeDocument ? 'Active' : '',
                    active_version_status_color: '',
                    signing_chain: $chain,
                    versions_count: $item->files->count(),
                    versions: $versions
                );
            })->toArray();

            return generalResponse(
                message: 'Success',
                data: $data
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete a master template together with its stored files on disk.
     *
     * @param  string  $templateUid  Uid of the master template to delete
     * @return array Response envelope with a success/error message
     *
     * @throws DataNotFound
     */
    public function deleteTemplate(string $templateUid): array
    {
        try {
            $template = $this->masterDocumentRepo->show([
                'where' => ['uid' => $templateUid],
                'select' => ['id'],
                'with' => [
                    'files:id,master_document_id,path',
                ],
            ]);

            if (! $template) {
                throw new DataNotFound('Document not found.');
            }

            // Delete files
            foreach ($template->files as $file) {
                if (Storage::exists($file->path)) {
                    Storage::delete($file->path);
                }
            }

            $template->delete();

            return generalResponse(
                message: __('notification.successDeleteMasterDocument')
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Approve or reject the pending-review version of a master document template.
     *
     * @param  ApprovalDocumentData  $payload  Approval decision (status + optional reason)
     * @param  string  $documentUid  Uid of the master document
     * @return array Response envelope with a success/error message
     *
     * @throws DataNotFound
     */
    public function approvalMasterDocument(ApprovalDocumentData $payload, string $documentUid): array
    {
        try {
            $document = $this->masterDocumentRepo->show([
                'where' => ['uid' => $documentUid],
                'select' => ['id'],
                'with' => [
                    'pendingDocument:id,path,created_by,master_document_id,version',
                ],
            ]);

            if (! $document) {
                throw new DataNotFound('Document not found.');
            }

            if (! $document->pendingDocument) {
                throw new DataNotFound('No pending document found.');
            }

            $isApproved = $payload->status == 1 ? true : false;

            $payloadUpdate = [
                'status' => $isApproved ? DocumentFileStatus::Active : DocumentFileStatus::Rejected,
                'rejected_by' => $isApproved ? null : Auth::id(),
                'approved_by' => ! $isApproved ? null : Auth::id(),
                'approval_note' => $payload->reason ?? null,
            ];

            $this->masterFileRepo->approveDocument($payloadUpdate, $document->pendingDocument);

            // TODO: Notify creator about approval

            return generalResponse(
                message: 'Document has been '.($isApproved ? 'approved' : 'rejected').' successfully'
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Build a renderable copy of a generated employee document.
     *
     * The stored document keeps its signature placeholders untouched; signatures are never
     * baked into it. Instead each render composites the currently applied signatures onto a
     * throwaway temp copy, leaving the base document pristine. Pass `$withSignatures = false`
     * to preview the document before signing (placeholders, no signatures overlaid).
     *
     * The returned `path` points at a temporary file the caller is responsible for deleting
     * after streaming it (see `is_temporary`).
     *
     * @param  string  $employeeDocumentUid  Uid of the generated employee document
     * @param  bool  $withSignatures  Whether to overlay applied signatures
     * @return array Response envelope containing the temporary document `path`
     *
     * @throws DataNotFound
     */
    public function renderEmployeeDocument(string $employeeDocumentUid, bool $withSignatures = true): array
    {
        try {
            $document = $this->employeeDocumentRepo->show([
                'where' => [
                    'uid' => $employeeDocumentUid,
                ],
                'select' => ['id', 'employee_id', 'document_snapshot', 'document_path'],
                'with' => [
                    'signatureTasks:id,employee_document_id,employee_signature_id,order,status',
                    'signatureTasks.employeeSignature:id,sign_path',
                ],
            ]);

            if (! $document) {
                throw new DataNotFound('Document not found.');
            }

            if (! Storage::disk('public')->exists($document->document_path)) {
                throw new DataNotFound('File not exists.');
            }

            $renderPath = $this->buildRenderableDocument($document, $withSignatures);

            return generalResponse(
                message: 'Success',
                data: [
                    'path' => $renderPath,
                    'is_temporary' => true,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Build a downloadable copy of a generated employee document, but only once it is fully
     * signed (completed).
     *
     * Access is limited to privileged roles (root/director/hrd) or a signer on the document.
     * The document is refused while any signature task is still outstanding. The returned `path`
     * points at a temporary file the caller is responsible for deleting after streaming it.
     *
     * @param  string  $employeeDocumentUid  Uid of the generated employee document
     * @return array Response envelope containing the temporary document `path`
     *
     * @throws DataNotFound
     * @throws DocumentNotCompleted
     */
    public function downloadCompletedDocument(string $employeeDocumentUid): array
    {
        try {
            $document = $this->employeeDocumentRepo->show([
                'where' => [
                    'uid' => $employeeDocumentUid,
                ],
                'select' => ['id', 'uid', 'employee_id', 'status', 'document_path'],
                'with' => [
                    'signatureTasks:id,employee_document_id,employee_id,employee_signature_id,order,status',
                    'signatureTasks.employeeSignature:id,sign_path',
                ],
            ]);

            if (! $document) {
                throw new DataNotFound('Document not found.');
            }

            $user = Auth::user();
            $isPrivileged = $user->hasRole([
                BaseRole::Root->value,
                BaseRole::Director->value,
                BaseRole::Hrd->value,
            ]);
            $isSigner = $document->signatureTasks->contains('employee_id', $user->employee_id);

            if (! $isPrivileged && ! $isSigner) {
                return errorResponse(__('notification.notAllowedToViewDocument'), [], 403);
            }

            if ($document->status !== Status::Completed) {
                throw new DocumentNotCompleted;
            }

            if (! Storage::disk('public')->exists($document->document_path)) {
                throw new DataNotFound('File not exists.');
            }

            $renderPath = $this->buildRenderableDocument($document, true);
            $pdfPath = $this->renderCompletedDocumentAsPdf($renderPath);

            return generalResponse(
                message: 'Success',
                data: [
                    'path' => $pdfPath,
                    'is_temporary' => true,
                    'mime' => 'application/pdf',
                    'extension' => 'pdf',
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Convert a rendered (signatures overlaid) .docx into a PDF for final download and drop the
     * intermediate .docx. Delivering the completed document as PDF guarantees the signature image
     * renders identically across every viewer (Word, LibreOffice, Google Docs mobile), which the
     * .docx does not because PhpWord embeds the signature as legacy VML.
     *
     * @param  string  $docxRenderPath  Path of the rendered .docx relative to the public disk
     * @return string Path of the generated PDF relative to the public disk
     *
     * @throws PdfConversionFailed
     */
    protected function renderCompletedDocumentAsPdf(string $docxRenderPath): string
    {
        $absoluteDocx = storage_path('app/public/'.$docxRenderPath);
        $absolutePdf = $this->pdfConverter->toPdf($absoluteDocx);

        Storage::disk('public')->delete($docxRenderPath);

        return dirname($docxRenderPath).'/'.basename($absolutePdf);
    }

    /**
     * Resolve the stored file path of a specific master template version for streaming.
     *
     * @param  string  $templateUid  Uid of the master template
     * @param  string  $versionId  Id of the template file version
     * @return array Response envelope containing the document `path`
     *
     * @throws DataNotFound
     */
    public function renderTemplateDocument(string $templateUid, string $versionId): array
    {
        try {
            $document = $this->masterDocumentRepo->show([
                'where' => [
                    'uid' => $templateUid,
                ],
                'select' => ['id'],
            ]);

            if (! $document) {
                throw new DataNotFound('Document is not found.');
            }

            $file = $this->masterFileRepo->show([
                'where' => [
                    'id' => $versionId,
                    'master_document_id' => $document->id,
                ],
                'select' => ['id', 'path'],
            ]);

            if (! Storage::exists($file->path)) {
                throw new DataNotFound('Version document is not found.');
            }

            return generalResponse(
                message: 'Success',
                data: [
                    'path' => $file->path,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Copy a source file into the target employee's documents directory.
     *
     * @param  string  $currentFilePath  Source path relative to the public disk
     * @param  Employee|Collection  $employee  Employee whose directory receives the copy
     * @return string The new file path relative to the public disk
     */
    protected function copyFileToEmployeeDirectory(string $currentFilePath, Employee|Collection $employee): string
    {
        $name = basename($currentFilePath);
        $file = storage_path('app/public/'.$currentFilePath);
        $target = "employees/{$employee->id}/documents/{$name}";

        Storage::disk('public')
            ->copy($currentFilePath, $target);

        return $target;
    }

    /**
     * Replace placeholders inside a Word document in place with their resolved values.
     *
     * @param  array<int, array{from: string, value: string}>  $placeholderMappings  Placeholder → value pairs
     * @param  string  $filepath  Document path relative to the public disk
     */
    protected function replaceDocumentContent(array $placeholderMappings, string $filepath): void
    {
        $realPath = storage_path('app/public/'.$filepath);

        $templateProcessor = new TemplateProcessor($realPath);
        foreach ($placeholderMappings as $placeholder) {
            $templateProcessor->setValue($placeholder['from'], $placeholder['value']);
        }

        $templateProcessor->saveAs($realPath);
    }

    /**
     * Build the list of employee columns to select and their keys, based on the template's
     * active placeholder mapping and the configured replacer columns.
     *
     * @param  MasterDocument  $document  Master document whose active version holds the mapping
     * @return array{columns: array<int, string>, keys: array<int, string>}
     */
    protected function getDocumentColumnsReplacer(MasterDocument $document): array
    {
        $keys = [];
        $mappingPlaceholders = $document->activeDocument->placeholder_mapping;

        $availableColumns = config('signature.available_replacer_column');
        $columns = array_map(function ($itemColumn) {
            return $itemColumn['column'] ?? null;
        }, array_filter($availableColumns, function ($item) use ($mappingPlaceholders) {
            return ! isset($item['relation']) && in_array($item['key'], $mappingPlaceholders);
        }));

        $columns['id'] = 'id';

        $keys = array_keys(array_filter($columns));

        // Remove null value
        $columns = array_values(array_filter($columns));

        return [
            'columns' => $columns,
            'keys' => $keys,
        ];
    }

    /**
     * Persist a generated document record against an employee with a "need sign" status.
     *
     * @param  string  $employeeDocumentPath  Path of the generated document for the employee
     * @param  Employee  $employee  Employee the document is assigned to
     */
    protected function assignDocumentToEmployee(string $employeeDocumentPath, Employee $employee)
    {
        $employeeDocument = $this->employeeDocumentRepo->store([
            'employee_id' => $employee->id,
            'status' => Status::NeedSign,
            'total_signer' => '',
            'document_snapshot' => '',
            'document_path' => '',
            'document_type_id' => '',
        ]);
    }

    /**
     * Generate a signable document from a template for one or more employees, resolving
     * placeholders and creating the per-signer signature tasks (transactional).
     *
     * @param  GenerateDocumentData  $payload  Target employee id(s) and version label
     * @param  string  $templateUid  Uid of the master template to generate from
     * @return array Response envelope with a success/error message
     *
     * @throws DataNotFound
     */
    public function generateDocument(GenerateDocumentData $payload, string $templateUid): array
    {
        try {
            $document = $this->loadTemplateForGeneration($templateUid);

            $columnReplacers = $this->getDocumentColumnsReplacer($document);
            $columns = $columnReplacers['columns'];
            $keys = $columnReplacers['keys'];

            $employee = $this->employeeRepo->show(
                uid: '',
                select: collect($columns)->join(','),
                where: "employee_id = '{$payload->employee_id}'"
            );

            if (! $employee) {
                throw new DataNotFound('Employee not found.');
            }

            $result = $this->createEmployeeDocument(
                $document,
                $employee,
                $columns,
                $keys,
                $this->buildDivisionSigners($document)
            );

            if ($result === null) {
                throw new DataNotFound('The employee still has the same document that has not been signed');
            }

            GenerateDocumentNotificationJob::dispatch($result['signer_ids'])->afterCommit();

            return generalResponse(
                message: 'Success to generate document for employee',
                data: [
                    'id' => $result['document']->uid,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Generate a signable document from a template for a whole audience of employees at once:
     * every active employee, everyone in a division, or everyone holding a position.
     *
     * Each employee is processed in its own transaction so one failure never aborts the batch.
     * Employees who already hold an unsigned document of the same type are skipped rather than
     * failed. Signer notifications are dispatched once for the whole run.
     *
     * @param  BulkGenerateDocumentData  $payload  Audience selection plus the source template
     * @return array Response envelope with a per-run summary (`total`, `generated`, `skipped`, `failed`)
     *
     * @throws DataNotFound
     */
    public function bulkGenerateDocument(BulkGenerateDocumentData $payload): array
    {
        try {
            $assignTo = AssignTo::from($payload->assign_to);

            $document = $this->loadTemplateForGeneration($payload->template_uid);

            $columnReplacers = $this->getDocumentColumnsReplacer($document);
            $columns = $columnReplacers['columns'];
            $keys = $columnReplacers['keys'];

            $divisionId = null;
            $positionId = null;

            if ($assignTo === AssignTo::Division) {
                $division = $this->divisionRepo->show(uid: $payload->division_id, select: 'id');

                if (! $division) {
                    throw new DataNotFound('Division not found.');
                }

                $divisionId = $division->id;
            }

            if ($assignTo === AssignTo::Position) {
                $position = $this->positionRepo->show(uid: $payload->position_id, select: 'id');

                if (! $position) {
                    throw new DataNotFound('Position not found.');
                }

                $positionId = $position->id;
            }

            $employees = $this->employeeRepo->getForSignatureDisbursement(
                select: $columns,
                assignTo: $assignTo,
                divisionId: $divisionId,
                positionId: $positionId
            )->unique('id')->values();

            $divisionSigners = $this->buildDivisionSigners($document);

            $generated = 0;
            $skipped = 0;
            $failed = 0;
            $signerIds = [];

            foreach ($employees as $employee) {
                try {
                    $result = $this->createEmployeeDocument($document, $employee, $columns, $keys, $divisionSigners);

                    if ($result === null) {
                        $skipped++;

                        continue;
                    }

                    $generated++;
                    $signerIds = array_merge($signerIds, $result['signer_ids']);
                } catch (\Throwable $th) {
                    $failed++;
                    logging('bulkGenerateDocument error: ', [$th->getMessage()]);
                }
            }

            if (! empty($signerIds)) {
                GenerateDocumentNotificationJob::dispatch(array_values(array_unique($signerIds)))->afterCommit();
            }

            return generalResponse(
                message: __('notification.successDisburseDocument'),
                data: [
                    'total' => $employees->count(),
                    'generated' => $generated,
                    'skipped' => $skipped,
                    'failed' => $failed,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Soft delete a single generated employee document, provided it is not yet completed, and
     * notify its target employee.
     *
     * Only privileged roles (root/director/hrd) may delete. Because the delete is soft and the
     * duplicate guard ignores soft-deleted rows, this frees the employee to have a fresh document
     * of the same type generated again.
     *
     * @param  string  $employeeDocumentUid  Uid of the generated document to delete
     * @return array Response envelope with the deleted document uid
     *
     * @throws DataNotFound
     * @throws CompletedDocumentNotDeletable
     */
    public function deleteGeneratedDocument(string $employeeDocumentUid): array
    {
        if (! $this->isDocumentManager()) {
            return errorResponse(__('notification.notAllowedToViewDocument'), [], 403);
        }

        DB::beginTransaction();
        try {
            $document = $this->employeeDocumentRepo->show([
                'where' => ['uid' => $employeeDocumentUid],
                'select' => ['id', 'uid', 'employee_id', 'status'],
            ]);

            if (! $document) {
                throw new DataNotFound('Document not found.');
            }

            if ($document->status === Status::Completed) {
                throw new CompletedDocumentNotDeletable;
            }

            $this->employeeDocumentRepo->delete($document);

            DocumentDeletedNotificationJob::dispatch([$document->employee_id])->afterCommit();

            DB::commit();

            return generalResponse(
                message: __('notification.successDeleteGeneratedDocument'),
                data: [
                    'uid' => $document->uid,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Soft delete many generated employee documents at once, skipping any that are already
     * completed or no longer exist, and notify each affected target employee.
     *
     * Only privileged roles (root/director/hrd) may delete.
     *
     * @param  BulkDeleteGeneratedDocumentData  $payload  Uids of the generated documents to delete
     * @return array Response envelope with a per-run summary (`requested`, `deleted`, `skipped`)
     */
    public function bulkDeleteGeneratedDocument(BulkDeleteGeneratedDocumentData $payload): array
    {
        if (! $this->isDocumentManager()) {
            return errorResponse(__('notification.notAllowedToViewDocument'), [], 403);
        }

        DB::beginTransaction();
        try {
            $documents = $this->employeeDocumentRepo->get([
                'whereIn' => ['uid' => $payload->uids],
                'select' => ['id', 'uid', 'employee_id', 'status'],
            ]);

            $deletable = $documents->filter(fn (EmployeeDocument $document) => $document->status !== Status::Completed);

            foreach ($deletable as $document) {
                $this->employeeDocumentRepo->delete($document);
            }

            $employeeIds = $deletable->pluck('employee_id')->unique()->values()->all();

            if (! empty($employeeIds)) {
                DocumentDeletedNotificationJob::dispatch($employeeIds)->afterCommit();
            }

            DB::commit();

            return generalResponse(
                message: __('notification.successDeleteGeneratedDocument'),
                data: [
                    'requested' => count($payload->uids),
                    'deleted' => $deletable->count(),
                    'skipped' => count($payload->uids) - $deletable->count(),
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Whether the authenticated user may manage (view/delete) generated documents.
     */
    protected function isDocumentManager(): bool
    {
        return Auth::user()->hasRole([
            BaseRole::Root->value,
            BaseRole::Director->value,
            BaseRole::Hrd->value,
        ]);
    }

    /**
     * Load a master template with everything a document generation needs: its active version,
     * the document type's ordered signers and each signer's PIC mapping.
     *
     * @param  string  $templateUid  Uid of the master template
     *
     * @throws DataNotFound
     */
    protected function loadTemplateForGeneration(string $templateUid): MasterDocument
    {
        $document = $this->masterDocumentRepo->show([
            'where' => ['uid' => $templateUid],
            'select' => ['id', 'name', 'document_type_id'],
            'with' => [
                'activeDocument:id,master_document_id,path,file_type,placeholder_mapping',
                'documentType' => function ($query) {
                    $query->selectRaw('id,code,name')
                        ->with([
                            'signers' => function ($querySigner) {
                                $querySigner->select(['id', 'type_id', 'division_id', 'order'])
                                    ->with([
                                        'signMapping:id,division_id,main_signer_id,delegate_signer_id',
                                    ])
                                    ->orderBy('order', 'asc');
                            },
                        ]);
                },
                'signers:id,master_document_id',
            ],
        ]);

        if (! $document) {
            throw new DataNotFound('Document not found.');
        }

        return $document;
    }

    /**
     * Build the fixed portion of a document's signing chain: the division PIC signers, in order.
     * The target employee is appended per-document by {@see self::createEmployeeDocument()}.
     *
     * @param  MasterDocument  $document  Template with its `documentType.signers.signMapping` loaded
     * @return array<int, array{employee_id: int, order: int}>
     */
    protected function buildDivisionSigners(MasterDocument $document): array
    {
        $divisionSigners = [];
        foreach ($document->documentType->signers as $documentSigner) {
            $divisionSigners[] = [
                'employee_id' => $documentSigner->signMapping->main_signer_id,
                'order' => $documentSigner->order,
            ];
        }

        return $divisionSigners;
    }

    /**
     * Generate a single employee's signable document: copy the active template, resolve its
     * placeholders from the employee's data, persist the document and create its signature tasks
     * (division PICs plus the employee).
     *
     * The whole operation runs in its own transaction and takes a row lock on the employee first,
     * so two concurrent or double-submitted requests for the same employee serialize: the second
     * one sees the document the first created and returns `null` instead of generating a duplicate.
     * Returns `null` whenever the employee already holds an unsigned document of the same type, so
     * the caller can decide whether that is an error (single generate) or a skip (bulk disburse).
     *
     * @param  MasterDocument  $document  Template loaded via {@see self::loadTemplateForGeneration()}
     * @param  Employee|Collection  $employee  Employee the document is generated for
     * @param  array<int, string>  $columns  Employee columns to inject
     * @param  array<int, string>  $keys  Placeholder keys aligned to `$columns`
     * @param  array<int, array{employee_id: int, order: int}>  $divisionSigners  Fixed signer chain
     * @return array{document: EmployeeDocument, signer_ids: array<int, int>}|null
     */
    protected function createEmployeeDocument(
        MasterDocument $document,
        Employee|Collection $employee,
        array $columns,
        array $keys,
        array $divisionSigners
    ): ?array {
        return DB::transaction(function () use ($document, $employee, $columns, $keys, $divisionSigners) {
            // Serialize concurrent generations for this employee. Locking the employee row makes
            // the duplicate check below reliable even when a competing request is mid-flight.
            Employee::query()
                ->whereKey($employee->id)
                ->lockForUpdate()
                ->first(['id']);

            $currentEmployeeDoc = $this->employeeDocumentRepo->show([
                'where' => [
                    'employee_id' => $employee->id,
                    'document_type_id' => $document->document_type_id,
                ],
                'whereIn' => [
                    'status' => [Status::Awaiting, Status::NeedSign],
                ],
            ]);

            if ($currentEmployeeDoc) {
                return null;
            }

            $employeeDocumentPath = $this->copyFileToEmployeeDirectory($document->activeDocument->path, $employee);

            // Mapping replacer
            $mappingPlaceholderReplacer = [];
            foreach ($columns as $key => $column) {
                $mappingPlaceholderReplacer[] = [
                    'from' => $keys[$key],
                    'value' => $employee->$column,
                ];
            }

            // Static date placeholder
            $mappingPlaceholderReplacer[] = ['from' => 'date', 'value' => date('d F Y')];

            // Replace the file content
            $this->replaceDocumentContent($mappingPlaceholderReplacer, $employeeDocumentPath);

            $employeeDocument = $this->employeeDocumentRepo->store([
                'employee_id' => $employee->id,
                'status' => Status::NeedSign,
                'total_signer' => $document->signers->count(),
                'document_snapshot' => $document->activeDocument->toArray(),
                'document_path' => $employeeDocumentPath,
                'document_type_id' => $document->document_type_id,
            ]);

            // Add the target employee as the final signer
            $signers = $divisionSigners;
            $signers[] = ['employee_id' => $employee->id, 'order' => count($signers) + 1];

            $employeeDocument->signatureTasks()->createMany($signers);

            return [
                'document' => $employeeDocument,
                'signer_ids' => collect($signers)->pluck('employee_id')->toArray(),
            ];
        });
    }

    /**
     * Guard that the given user is an eligible signer who has not already signed.
     *
     * @param  Collection  $signatureTasks  Signature tasks of the document
     * @param  Authenticatable|null  $user  Currently authenticated user
     *
     * @throws UserAlreadySigned
     * @throws UserNotHaveAccessToSign
     */
    protected function isSignerValid(Collection $signatureTasks, ?Authenticatable $user): void
    {
        $search = $signatureTasks->where('employee_id', $user->employee_id)
            ->values();

        if ($search->isNotEmpty() && $search[0]->status == SignatureTaskStatus::Signed) {
            throw new UserAlreadySigned;
        }

        if ($search->isEmpty()) {
            throw new UserNotHaveAccessToSign;
        }
    }

    /**
     * Validate the signing OTP for the authenticated signer.
     *
     * On success the stored OTP is cleared so it cannot be replayed; marking the task as
     * signed happens later in the signing flow, not here.
     *
     * @param  string  $employeeDocumentUid  Uid of the employee document being signed
     * @param  string  $otp  The 6-digit OTP submitted by the signer
     * @return array Response envelope; error message when the OTP is wrong or expired
     *
     * @throws DataNotFound
     */
    public function validateOtp(string $employeeDocumentUid, string $otp): array
    {
        try {
            $user = Auth::user();

            $document = $this->employeeDocumentRepo->show([
                'where' => [
                    'uid' => $employeeDocumentUid,
                ],
                'select' => ['id'],
                'with' => ['signatureTasks'],
            ]);

            if (! $document) {
                throw new DataNotFound('Document not found.');
            }

            $this->isSignerValid($document->signatureTasks, $user);

            $task = $document->signatureTasks
                ->firstWhere('employee_id', $user->employee_id);

            if (! $task->otp || ! hash_equals($task->otp, $otp)) {
                return errorResponse(message: __('notification.otpMismatch'));
            }

            if (! $task->otp_expired_at || $task->otp_expired_at->isPast()) {
                return errorResponse(message: __('notification.otpHasBeenExpired'));
            }

            $this->signatureTaskRepo->update($task, [
                'otp' => null,
                'otp_expired_at' => null,
            ]);

            return generalResponse(
                message: 'Success'
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Generate and dispatch a one-time password the signer uses to sign the document.
     *
     * @param  string  $employeeDocumentUid  Uid of the employee document to sign
     * @return array Response envelope with a success/error message
     *
     * @throws DataNotFound
     */
    public function generateSignOtp(string $employeeDocumentUid): array
    {
        try {
            $user = Auth::user();

            $document = $this->employeeDocumentRepo->show([
                'where' => [
                    'uid' => $employeeDocumentUid,
                ],
                'select' => ['id', 'document_type_id'],
                'with' => ['signatureTasks'],
            ]);

            if (! $document) {
                throw new DataNotFound('Document not found.');
            }

            $this->isSignerValid($document->signatureTasks, $user);

            SendOtpSignJob::dispatch($user, $document->id);

            return generalResponse(
                message: 'Please check registered email to see OTP code'
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Resolve the placeholder name a signer's signature occupies from their signing `order`.
     *
     * The final signer (highest order) fills the `employeeSignature` placeholder; every earlier
     * signer fills their positional `signature{order}` placeholder (e.g. order 2 -> `signature2`).
     *
     * @param  int  $order  The signer's order in the document
     * @param  int  $lastOrder  The highest order among the document's signers
     */
    protected function resolveSignaturePlaceholder(int $order, int $lastOrder): string
    {
        return $order == $lastOrder
            ? 'employeeSignature'
            : 'signature'.$order;
    }

    /**
     * Composite the currently applied signatures onto a throwaway copy of a generated document.
     *
     * The base document is never mutated: it keeps its signature placeholders so any set of
     * signatures can be re-rendered at any time. Each render copies the base to a temp file and
     * overlays the signatures of every already-signed task onto their placeholder. The caller is
     * responsible for deleting the returned temp file after use.
     *
     * @param  EmployeeDocument  $document  Generated document with its `signatureTasks.employeeSignature` loaded
     * @param  bool  $withSignatures  When false, produce a clean preview with no signatures overlaid
     * @return string Path of the temporary rendered document relative to the public disk
     */
    protected function buildRenderableDocument(EmployeeDocument $document, bool $withSignatures = true): string
    {
        $this->createFolder('signature/tmp');

        $tempPath = 'signature/tmp/render_'.$document->id.'_'.strtotime('now').rand(100, 999).'.docx';
        Storage::disk('public')->copy($document->document_path, $tempPath);

        $appliedTasks = $document->signatureTasks
            ->where('status', SignatureTaskStatus::Signed)
            ->filter(fn (EmployeeSignatureTask $task) => $task->employeeSignature !== null);

        if (! $withSignatures || $appliedTasks->isEmpty()) {
            return $tempPath;
        }

        $lastOrder = $document->signatureTasks->max('order');
        $realTempPath = storage_path('app/public/'.$tempPath);

        $templateProcessor = new TemplateProcessor($realTempPath);
        foreach ($appliedTasks as $task) {
            $templateProcessor->setImageValue(
                $this->resolveSignaturePlaceholder($task->order, $lastOrder),
                [
                    'path' => storage_path('app/public/'.$task->employeeSignature->sign_path),
                    'width' => 150,
                    'height' => 60,
                    'ratio' => true,
                ]
            );
        }
        $templateProcessor->saveAs($realTempPath);

        return $tempPath;
    }

    /**
     * Apply the authenticated employee's saved signature to their signing task on a document.
     *
     * The signature is recorded as a reference on the signature task ({@see EmployeeSignatureTask::employeeSignature()}),
     * never baked into the stored document. It is composited onto the document only at render
     * time by {@see self::buildRenderableDocument()}, which is what makes a signature editable
     * afterwards (see {@see self::updateAppliedSignature()}). The task is then marked as signed.
     *
     * @param  string  $signatureUid  Uid of the employee's saved signature to apply
     * @param  string  $employeeDocumentUid  Uid of the employee document being signed
     * @return array Response envelope with a success/error message
     *
     * @throws DataNotFound
     */
    public function applySignatureToDocument(string $signatureUid, string $employeeDocumentUid): array
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            $employeeId = $user->employee_id;

            $signature = $this->employeeSignatureRepo->show([
                'where' => [
                    'uid' => $signatureUid,
                    'employee_id' => $employeeId,
                ],
                'select' => ['id', 'uid', 'employee_id', 'sign_path'],
            ]);

            if (! $signature) {
                throw new DataNotFound('Signature not found.');
            }

            $document = $this->employeeDocumentRepo->show([
                'where' => ['uid' => $employeeDocumentUid],
                'select' => ['id', 'uid', 'employee_id', 'status'],
                'with' => ['signatureTasks'],
            ]);

            if (! $document) {
                throw new DataNotFound('Document not found.');
            }

            $this->isSignerValid($document->signatureTasks, $user);

            $task = $document->signatureTasks->firstWhere('employee_id', $employeeId);

            $this->signatureTaskRepo->update($task, [
                'employee_signature_id' => $signature->id,
                'status' => SignatureTaskStatus::Signed,
                'signed_at' => now(),
            ]);

            $this->syncDocumentCompletion($document);

            DB::commit();

            return generalResponse(
                message: __('notification.successApplySignature'),
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Replace the signature the authenticated employee already applied to a document.
     *
     * Because signatures are overlaid at render time and not baked in, replacing one only swaps
     * the reference on the signer's task. To protect the integrity of the signing chain a signer
     * may only change their signature while it is still the latest in the chain: it is refused
     * once a later signer has signed or the document is fully completed.
     *
     * @param  string  $signatureUid  Uid of the replacement signature
     * @param  string  $employeeDocumentUid  Uid of the employee document
     * @return array Response envelope with a success/error message
     *
     * @throws DataNotFound
     * @throws UserNotHaveAccessToSign
     * @throws SignatureNotEditable
     */
    public function updateAppliedSignature(string $signatureUid, string $employeeDocumentUid): array
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            $employeeId = $user->employee_id;

            $signature = $this->employeeSignatureRepo->show([
                'where' => [
                    'uid' => $signatureUid,
                    'employee_id' => $employeeId,
                ],
                'select' => ['id', 'uid', 'employee_id', 'sign_path'],
            ]);

            if (! $signature) {
                throw new DataNotFound('Signature not found.');
            }

            $document = $this->employeeDocumentRepo->show([
                'where' => ['uid' => $employeeDocumentUid],
                'select' => ['id', 'uid', 'status'],
                'with' => ['signatureTasks'],
            ]);

            if (! $document) {
                throw new DataNotFound('Document not found.');
            }

            $task = $document->signatureTasks->firstWhere('employee_id', $employeeId);

            if (! $task) {
                throw new UserNotHaveAccessToSign;
            }

            if ($task->status !== SignatureTaskStatus::Signed) {
                throw new SignatureNotEditable('You have not signed this document yet.');
            }

            $hasLaterSigner = $document->signatureTasks
                ->where('order', '>', $task->order)
                ->contains('status', SignatureTaskStatus::Signed);

            if ($hasLaterSigner || $document->status === Status::Completed) {
                throw new SignatureNotEditable('Your signature can no longer be changed because a later signer has already signed.');
            }

            $this->signatureTaskRepo->update($task, [
                'employee_signature_id' => $signature->id,
            ]);

            DB::commit();

            return generalResponse(
                message: __('notification.successUpdateAppliedSignature'),
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Flip a document to completed once every signature task has been signed, notifying the
     * document owner in-app when it does.
     *
     * The passed document must have its `signatureTasks` relation loaded and carry `employee_id`.
     * No-op while any task is still outstanding.
     *
     * @param  EmployeeDocument  $document  Document whose signing progress is being evaluated
     */
    protected function syncDocumentCompletion(EmployeeDocument $document): void
    {
        $allSigned = $document->signatureTasks
            ->every(fn (EmployeeSignatureTask $task) => $task->status === SignatureTaskStatus::Signed);

        if (! $allSigned || $document->status === Status::Completed) {
            return;
        }

        $this->employeeDocumentRepo->update($document, [
            'status' => Status::Completed,
        ]);

        DocumentCompletedNotificationJob::dispatch($document->employee_id, $document->uid)
            ->afterCommit();
    }

    /**
     * Fetch a paginated list of generated employee documents.
     *
     * @return array Response envelope with the paginated documents
     */
    public function generatedDocumentList(): array
    {
        try {
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;

            $user = Auth::user();
            $isPrivileged = $user->hasRole([
                BaseRole::Root->value,
                BaseRole::Director->value,
                BaseRole::Hrd->value,
            ]);

            if (! $isPrivileged) {
                return errorResponse(__('notification.notAllowedToViewDocument'), [], 403);
            }

            $data = $this->employeeDocumentRepo->get([
                'select' => ['id', 'uid', 'employee_id', 'status', 'document_type_id'],
                'skip' => $page,
                'take' => $itemsPerPage,
                'with' => [
                    'employee:id,name,email,avatar_color',
                    'documentType:id,name',
                    'documentType.masterDocument:id,document_type_id,name,current_active_version_text',
                    'signatureTasks:id,employee_document_id,status',
                ],
            ])->map(function ($item) {
                return new GeneratedDocumentListData(
                    uid: $item->uid,
                    document_name: $item->documentType->masterDocument->name,
                    version: $item->documentType->masterDocument->current_active_version_text,
                    type: $item->documentType->name,
                    status: $item->status->label(),
                    employee: new DetailDocumentSignEmployeeData(
                        name: $item->employee->name,
                        initials: getInitialName($item->employee->name),
                        color: $item->employee->avatar_color ?? generateRandomColor($item->employee->email)
                    ),
                    signers: $item->signatureTasks->map(function ($task) {
                        return [
                            'status' => $task->status->value,
                        ];
                    })->toArray()
                );
            })->all();

            $totalData = $this->employeeDocumentRepo->get([
                'select' => ['id', 'status'],
            ]);

            $inProgress = $totalData->whereIn('status', [Status::NeedSign, Status::Awaiting])
                ->count();
            $awaiting = $totalData->where('status', Status::Awaiting)
                ->count();
            $completed = $totalData->where('status', Status::Completed)
                ->count();

            return generalResponse(
                message: 'Success',
                data: [
                    'paginated' => $data,
                    'totalData' => $totalData->count(),
                    'stats' => [
                        'inProgress' => $inProgress,
                        'awaiting' => $awaiting,
                        'completed' => $completed,
                    ],
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Fetch a paginated list of generated documents the authenticated user must sign.
     *
     * Intended for non-privileged users (not root/director/hrd): only documents on which
     * the current employee is a signer are returned. Each item's `signers` carry a
     * per-signer `is_me` flag marking the current user's row.
     *
     * @return array Response envelope with the paginated documents
     */
    public function myGeneratedDocumentList(): array
    {
        try {
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;

            $employeeId = Auth::user()->employee_id;

            $signerScope = [
                'whereHas' => [
                    'signatureTasks' => fn ($query) => $query->where('employee_id', $employeeId),
                ],
            ];

            $data = $this->employeeDocumentRepo->get(array_merge($signerScope, [
                'select' => ['id', 'uid', 'status', 'document_type_id'],
                'skip' => $page,
                'take' => $itemsPerPage,
                'with' => [
                    'documentType:id,name',
                    'documentType.masterDocument:id,document_type_id,name,current_active_version_text',
                    'signatureTasks' => fn ($query) => $query
                        ->select(['id', 'employee_document_id', 'employee_id', 'status', 'order'])
                        ->orderBy('order'),
                    'signatureTasks.employee:id,name,position_id',
                    'signatureTasks.employee.position:id,name',
                ],
            ]))->map(function ($item) use ($employeeId) {
                return new MyGeneratedDocumentListData(
                    uid: $item->uid,
                    document_name: $item->documentType->masterDocument->name,
                    version: $item->documentType->masterDocument->current_active_version_text,
                    type: $item->documentType->name,
                    signers: $item->signatureTasks->map(function ($task) use ($employeeId) {
                        return new MyDocumentSignerData(
                            role: $task->employee->position->name,
                            name: $task->employee->name,
                            status: $task->status->value,
                            is_me: $task->employee_id == $employeeId,
                        );
                    })->all()
                );
            })->all();

            $totalData = $this->employeeDocumentRepo->get(array_merge($signerScope, [
                'select' => ['id'],
            ]))->count();

            return generalResponse(
                message: 'Success',
                data: [
                    'paginated' => $data,
                    'totalData' => $totalData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get a generated document's detail including each signer and their signing progress.
     *
     * @param  string  $documentUid  Uid of the generated employee document
     * @return array Response envelope with the document sign detail
     */
    public function documentSignDetail(string $documentUid): array
    {
        try {
            /** @var array<DetailDocumentSignData> */
            $output = [];

            $employeeDocument = $this->employeeDocumentRepo->show([
                'where' => [
                    'uid' => $documentUid,
                ],
                'select' => ['id', 'uid', 'employee_id', 'status', 'signers_detail', 'document_path', 'document_type_id', 'document_snapshot'],
                'with' => [
                    'documentType:id,name',
                    'documentType.masterDocument:id,uid,document_type_id,current_active_version_text,name',
                    'employee:id,uid,name,avatar_color,email',
                    'signatureTasks',
                    'signatureTasks.employee:id,email,position_id,name',
                    'signatureTasks.employee.position:id,name',
                ],
            ]);

            if (! $employeeDocument) {
                throw new DataNotFound('Employee document is not found');
            }

            $user = Auth::user();
            $isPrivileged = $user->hasRole([
                BaseRole::Root->value,
                BaseRole::Director->value,
                BaseRole::Hrd->value,
            ]);
            $isSigner = $employeeDocument->signatureTasks
                ->contains('employee_id', $user->employee_id);

            if (! $isPrivileged && ! $isSigner) {
                return errorResponse(__('notification.notAllowedToViewDocument'), [], 403);
            }

            /** @var array<int, DetailDocumentSignSignersData> */
            $signers = [];

            foreach ($employeeDocument->signatureTasks as $task) {
                $signers[] = new DetailDocumentSignSignersData(
                    role: $task->employee->position->name,
                    name: $task->employee->name,
                    email: $task->employee->email,
                    status: $task->status->value,
                    signed_at: date('d F Y, H:i', strtotime($task->signed_at))
                );
            }

            $output = new DetailDocumentSignData(
                uid: $employeeDocument->uid,
                template_uid: $employeeDocument->documentType->masterDocument->uid,
                version_id: $employeeDocument->document_snapshot['id'] ?? 0,
                document_name: $employeeDocument->documentType->masterDocument->name,
                version: $employeeDocument->documentType->masterDocument->current_active_version_text,
                type: $employeeDocument->documentType->name,
                can_sign: $employeeDocument->isMyTurnToSign(),
                status: $employeeDocument->status->label(),
                is_completed: $employeeDocument->status === Status::Completed ? true : false,
                employee: new DetailDocumentSignEmployeeData(
                    name: $employeeDocument->employee->name,
                    initials: getInitialName($employeeDocument->employee->name),
                    color: $employeeDocument->employee->avatar_color ?? generateRandomColor($employeeDocument->employee->email)
                ),
                signers: $signers
            );

            return generalResponse(
                message: 'Success',
                data: DetailDocumentSignData::from($output)->toArray()
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * List the organization's signatories and each division's PIC mapping.
     *
     * @return array Response envelope with the signatories list
     */
    public function listSignatories(): array
    {
        try {
            /** @var array<SignatoriesListData> */
            $output = [];

            $orgSignatories = [];
            $mandatoryDivisionUids = getSettingByKey('global_signatory_divisions');

            /** @var array<int, SignatoriesDivisionPicData> */
            $divisionPics = $this->signatoriesMappingRepo->getMappingWithHeadCount();

            /** @var array<int, SelectedOrgSignatureSignerData> */
            $signerOptions = [];

            $pmPosition = getSettingByKey('position_as_project_manager');
            $directorPosition = getSettingBykey('position_as_directors');

            if ($pmPosition) {
                $positionCondition = "'".collect(json_decode($pmPosition, true))->join("','")."'";

                // Combine pm position and director position
                $combinePosition = json_decode($pmPosition, true);
                if ($directorPosition) {
                    $directorDecodedPosition = json_decode($directorPosition, true);
                    $combinePosition = array_merge($combinePosition, $directorDecodedPosition);

                    // remove duplicate
                    $combinePosition = array_values(array_unique($combinePosition));
                    $positionCondition = "'".collect($combinePosition)->join("','")."'";
                }

                $positionData = $this->positionRepo->list(
                    select: 'id',
                    where: "uid IN ({$positionCondition})"
                );

                $managers = $this->employeeRepo->getActiveProjectManager($positionData->pluck('id')->toArray());

                foreach ($managers as $manager) {
                    $signerOptions[] = new SelectedOrgSignatureSignerData(
                        uid: $manager->uid,
                        name: $manager->name,
                        role: $manager->position->name,
                        initial: getInitialName($manager->name),
                        color: $manager->avatar_color
                    );
                }
            }

            if ($mandatoryDivisionUids) {
                $filteredKey = [];
                foreach (json_decode($mandatoryDivisionUids, true) as $mandatoryDivisionUid) {
                    $search = collect($divisionPics)->search(function ($itemSearch) use ($mandatoryDivisionUid) {
                        return $itemSearch->division_uid == $mandatoryDivisionUid;
                    });

                    if (gettype($search) !== 'boolean') {
                        $filteredKey[] = $divisionPics[$search]->uid;

                        $orgSignatories[] = new OrgSignatoriesListData(
                            role_key: $divisionPics[$search]->uid,
                            role_label: $divisionPics[$search]->division_name,
                            description: '',
                            signer: $divisionPics[$search]->pic,
                            signer_options: $divisionPics[$search]->signer_options
                        );
                    }
                }

                $filteredDivisionPics = array_values(array_filter($divisionPics, function ($filter) use ($filteredKey) {
                    return ! in_array($filter->uid, $filteredKey);
                }));
            }

            $output = new SignatoriesListData(
                org_signatories: $orgSignatories,
                division_pics: $filteredDivisionPics ?? $divisionPics,
                signer_options: $signerOptions
            );

            return generalResponse(
                message: 'Success',
                data: SignatoriesListData::from($output)->toArray()
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Assign a PIC (main and/or delegate) as the signer for a division's signatory mapping.
     *
     * @param  AssignSignatoriesData  $payload  Signatory assignment data
     * @param  string  $mappingUid  Uid of the signatories mapping to update
     * @return array Response envelope with a success/error message
     */
    public function assignSignatories(AssignSignatoriesData $payload, string $mappingUid): array
    {
        try {
            $mapping = $this->signatoriesMappingRepo->show([
                'select' => ['id', 'main_signer_id', 'delegate_signer_id'],
                'where' => ['uid' => $mappingUid],
            ]);

            if (! $mapping) {
                throw new DataNotFound('Signatories division is not found.');
            }

            $payloadUpdate = [];

            if ($payload->pic_uid) {
                $signer = $this->employeeRepo->show(uid: $payload->pic_uid, select: 'id');
                $payloadUpdate['main_signer_id'] = $signer->id;
            }

            if ($payload->delegate_uid) {
                $delegate = $this->employeeRepo->show(uid: $payload->delegate_uid, select: 'id');
                $payloadUpdate['delegate_signer_id'] = $delegate->id;
            }

            $this->signatoriesMappingRepo->update($mapping, $payloadUpdate);

            return generalResponse(
                message: 'Success'
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Transform an employee signature model into its API representation.
     */
    protected function transformEmployeeSignature(EmployeeSignature $signature): EmployeeSignatureData
    {
        return new EmployeeSignatureData(
            uid: $signature->uid,
            sign_url: asset('storage/'.$signature->sign_path),
            is_active: (bool) $signature->is_active,
            created_at: $signature->created_at?->format('d F Y H:i'),
        );
    }

    /**
     * Deactivate every currently active signature belonging to an employee.
     */
    protected function deactivateEmployeeSignatures(int $employeeId): void
    {
        $this->employeeSignatureRepo->get([
            'where' => [
                'employee_id' => $employeeId,
                'is_active' => true,
            ],
            'select' => ['id', 'is_active'],
        ])->each(function (EmployeeSignature $signature) {
            $this->employeeSignatureRepo->update($signature, ['is_active' => false]);
        });
    }

    /**
     * List every signature the authenticated employee has saved.
     *
     * @return array Response envelope with a list of `EmployeeSignatureData`
     */
    public function listEmployeeSignatures(): array
    {
        try {
            $employeeId = Auth::user()->employee_id;

            $data = $this->employeeSignatureRepo->get([
                'where' => ['employee_id' => $employeeId],
                'select' => ['id', 'uid', 'employee_id', 'is_active', 'sign_path', 'created_at'],
                'orderBy' => ['is_active' => 'desc', 'created_at' => 'desc'],
            ])->map(fn (EmployeeSignature $signature) => $this->transformEmployeeSignature($signature))
                ->all();

            return generalResponse(
                message: 'Success',
                data: $data,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Upload a new signature image for the authenticated employee and mark it active,
     * deactivating any previously active signature (transactional).
     *
     * @param  StoreEmployeeSignatureData  $payload  Uploaded signature image
     * @return array Response envelope with the stored `EmployeeSignatureData`
     */
    public function storeEmployeeSignature(StoreEmployeeSignatureData $payload): array
    {
        DB::beginTransaction();
        try {
            $employeeId = Auth::user()->employee_id;

            $directory = 'signatures/employees/'.$employeeId;
            $this->createFolder($directory);

            $filename = 'signature_'.$employeeId.'_'.strtotime('now').rand(100, 999).'.'.$payload->signature->getClientOriginalExtension();
            $storedPath = Storage::disk('public')->putFileAs($directory, $payload->signature, $filename);

            if (! $storedPath) {
                throw new FailedToUploadFile;
            }

            $this->deactivateEmployeeSignatures($employeeId);

            $signature = $this->employeeSignatureRepo->store([
                'employee_id' => $employeeId,
                'is_active' => true,
                'sign_path' => $storedPath,
            ]);

            DB::commit();

            return generalResponse(
                message: __('notification.successStoreEmployeeSignature'),
                data: $this->transformEmployeeSignature($signature)->toArray(),
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Mark one of the authenticated employee's signatures active, deactivating the rest
     * (transactional).
     *
     * @param  string  $signatureUid  Uid of the signature to activate
     * @return array Response envelope with a success/error message
     *
     * @throws DataNotFound
     */
    public function setActiveEmployeeSignature(string $signatureUid): array
    {
        DB::beginTransaction();
        try {
            $employeeId = Auth::user()->employee_id;

            $signature = $this->employeeSignatureRepo->show([
                'where' => [
                    'uid' => $signatureUid,
                    'employee_id' => $employeeId,
                ],
                'select' => ['id', 'uid', 'employee_id', 'is_active', 'sign_path', 'created_at'],
            ]);

            if (! $signature) {
                throw new DataNotFound('Signature not found.');
            }

            $this->deactivateEmployeeSignatures($employeeId);
            $this->employeeSignatureRepo->update($signature, ['is_active' => true]);

            DB::commit();

            return generalResponse(
                message: __('notification.successUpdateEmployeeSignature'),
                data: $this->transformEmployeeSignature($signature->refresh())->toArray(),
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Delete one of the authenticated employee's signatures together with its stored file.
     *
     * @param  string  $signatureUid  Uid of the signature to delete
     * @return array Response envelope with a success/error message
     *
     * @throws DataNotFound
     */
    public function deleteEmployeeSignature(string $signatureUid): array
    {
        try {
            $employeeId = Auth::user()->employee_id;

            $signature = $this->employeeSignatureRepo->show([
                'where' => [
                    'uid' => $signatureUid,
                    'employee_id' => $employeeId,
                ],
                'select' => ['id', 'uid', 'employee_id', 'sign_path'],
            ]);

            if (! $signature) {
                throw new DataNotFound('Signature not found.');
            }

            $isApplied = $this->signatureTaskRepo->get([
                'where' => ['employee_signature_id' => $signature->id],
                'whereHas' => ['employeeDocument' => null],
                'select' => ['id'],
            ])->isNotEmpty();

            if ($isApplied) {
                throw new SignatureNotEditable('This signature is applied to a document and cannot be deleted.');
            }

            if (Storage::disk('public')->exists($signature->sign_path)) {
                Storage::disk('public')->delete($signature->sign_path);
            }

            $this->employeeSignatureRepo->delete($signature);

            return generalResponse(
                message: __('notification.successDeleteEmployeeSignature'),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
