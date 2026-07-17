<?php

namespace Modules\Hrd\Services;

use App\Data\Hrd\Signature\ApprovalDocumentData;
use App\Data\Hrd\Signature\AssignSignatoriesData;
use App\Data\Hrd\Signature\BulkCreateDocumentTypeData;
use App\Data\Hrd\Signature\BulkDeleteDocumentTypeData;
use App\Data\Hrd\Signature\BulkUpdateDocumentTypeData;
use App\Data\Hrd\Signature\CreateDocumentTypeData;
use App\Data\Hrd\Signature\CreateTemplateData;
use App\Data\Hrd\Signature\DefaultSignerItemData;
use App\Data\Hrd\Signature\DetectPlaceholderData;
use App\Data\Hrd\Signature\GenerateDocumentData;
use App\Data\Hrd\Signature\ListDocumentTypeData;
use App\Data\Hrd\Signature\OrgSignatoriesListData;
use App\Data\Hrd\Signature\OutputListDocumentTypeData;
use App\Data\Hrd\Signature\SelectedOrgSignatureSignerData;
use App\Data\Hrd\Signature\SignatoriesDivisionPicData;
use App\Data\Hrd\Signature\SignatoriesListData;
use App\Data\Hrd\Signature\TemplateListData;
use App\Data\Hrd\Signature\UpdateDocumentTypeData;
use App\Data\Hrd\Signatured\DocumentVersionListData;
use App\Enums\Hrd\Signature\Template\DocumentFileStatus;
use App\Enums\Hrd\Signature\Template\Status;
use App\Exceptions\DataNotFound;
use App\Exceptions\DetectPlaceholderFailed;
use App\Exceptions\FailedToUploadFile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Company\Repository\DivisionRepository;
use Modules\Company\Repository\PositionRepository;
use Modules\Hrd\Exceptions\DocumentTypeInUse;
use Modules\Hrd\Exceptions\TemplateStillHavePendingReview;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\MasterDocument;
use Modules\Hrd\Repository\DocumentTypeRepository;
use Modules\Hrd\Repository\EmployeeDocumentRepository;
use Modules\Hrd\Repository\EmployeeRepository;
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
        private readonly EmployeeDocumentRepository $employeeDocumentRepo
    ) {}

    /**
     * Parsing document type payload
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
     * Fetch list of document types
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
     * Create document type
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
     * Bulk create document type
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
     * Bulk delete document types
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
     * Update current document type
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
     * Bulk update document types
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

    protected function createFolder($path): void
    {
        if (Storage::disk('public')->directoryExists($path)) {
            Storage::disk('public')->makeDirectory($path);
        }
    }

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
     * What will do in this function:
     * - validate signature placeholder -> should be match with document type signer -> done
     * - detect all placeholder document -> done
     * - provide available placeholder from system -> done
     * - decide user can go to preview or note
     * - detect missing parameters -> if there any document placeholder that is not match with available params from system
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
     * Create master template
     */
    public function createTemplate(CreateTemplateData $payload): array
    {
        DB::beginTransaction();
        try {
            $documentType = $this->documentTypeRepo->show([
                'id' => $payload->document_type_id,
                'select' => ['id', 'code'],
                'relation' => [
                    'signers' => function ($query) {
                        $query->selectRaw('id,type_id,division_id,order')
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
     * List of available master templates
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
     * Delete selected master document
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

    public function approvalMasterDocument(ApprovalDocumentData $payload, string $documentUid): array
    {
        try {
            $document = $this->masterDocumentRepo->show([
                'uid' => $documentUid,
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
     * Get real file path of the document to stream in frontend
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
     * Copy current file to new employee directory
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

    protected function replaceDocumentContent(array $placeholderMappings, string $filepath): void
    {
        $realPath = storage_path('app/public/' . $filepath);

        $templateProcessor = new TemplateProcessor($realPath);
        foreach ($placeholderMappings as $placeholder) {
            $templateProcessor->setValue($placeholder['from'], $placeholder['value']);
        }

        $templateProcessor->saveAs($realPath);
    }

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
            'keys' => $keys
        ];
    }

    protected function assignDocumentToEmployee(string $employeeDocumentPath, Employee $employee)
    {
        $employeeDocument = $this->employeeDocumentRepo->store([
            'employee_id' => $employee->id,
            'status' => Status::NeedSign,
            'total_signer' => '',
            'document_snapshot' => '',
            'document_path' => '',
            'document_type_id' => ''
        ]);
    }

    public function generateDocument(GenerateDocumentData $payload, string $templateUid): array
    {
        DB::beginTransaction();
        try {
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
                                            'signMapping:id,division_id,main_signer_id,delegate_signer_id'
                                        ])
                                        ->orderBy('order', 'asc');
                                },
                            ]);
                    },
                    'signers:id,master_document_id'
                ],
            ]);

            if (! $document) {
                throw new DataNotFound('Document not found.');
            }

            $columnReplacers = $this->getDocumentColumnsReplacer($document);
            $columns = $columnReplacers['columns'];
            $keys = $columnReplacers['keys'];

            $employee = $this->employeeRepo->show(
                uid: '',
                select: collect($columns)->join(','),
                where: "uid = '{$payload->employee_id}'"
            );

            if (! $employee) {
                throw new DataNotFound('Employee not found.');
            }

            $currentEmployeeDoc = $this->employeeDocumentRepo->show([
                'where' => [
                    'employee_id' => $employee->id,
                    'document_type_id' => $document->document_type_id,
                    ],
                'whereIn' => [
                    'status' => [Status::Awaiting, Status::NeedSign]
                ]
            ]);

            if ($currentEmployeeDoc) {
                throw new DataNotFound('The employee still has the same document that has not been signed');
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

            $fixFile = asset('storage/' . $employeeDocumentPath);

            $employeeDocument = $this->employeeDocumentRepo->store([
                'employee_id' => $employee->id,
                'status' => Status::NeedSign,
                'total_signer' => $document->signers->count(),
                'document_snapshot' => $document->activeDocument->toArray(),
                'document_path' => $employeeDocumentPath,
                'document_type_id' => $document->document_type_id
            ]);

            $divisionSigners = [];
            foreach ($document->documentType->signers as $documentSigner) {
                $divisionSigners[] = [
                    'employee_id' => $documentSigner->signMapping->main_signer_id,
                    'order' => $documentSigner->order
                ];
            }

            // Add a target user
            array_push($divisionSigners, ['employee_id' => $employee->id, 'order' => count($divisionSigners) + 1]);

            $employeeDocument->signatureTasks()->createMany($divisionSigners);

            DB::commit();

            return generalResponse(
                message: 'Success',
                data: [
                    'file_path' => $fixFile
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorResponse($th);
        }
    }

    public function documentSignDetail(string $documentUid)
    {
        try {
            $output = [];

            $document = $this->masterDocumentRepo->show([
                'where' => ['uid' => $documentUid],
                'select' => ['id', 'name', 'document_type_id'],
                'with' => [
                    'activeDocument:id,master_document_id,created_by',
                ],
            ]);

            return generalResponse(
                message: 'Success',
                data: []
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

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
     * Assign PIC as signer in division signatories
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
}
