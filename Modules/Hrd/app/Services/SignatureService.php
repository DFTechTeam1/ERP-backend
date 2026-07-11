<?php

namespace Modules\Hrd\Services;

use App\Data\Hrd\Signature\BulkCreateDocumentTypeData;
use App\Data\Hrd\Signature\BulkDeleteDocumentTypeData;
use App\Data\Hrd\Signature\BulkUpdateDocumentTypeData;
use App\Data\Hrd\Signature\CreateDocumentTypeData;
use App\Data\Hrd\Signature\CreateTemplateData;
use App\Data\Hrd\Signature\DefaultSignerItemData;
use App\Data\Hrd\Signature\DetectPlaceholderData;
use App\Data\Hrd\Signature\ListDocumentTypeData;
use App\Data\Hrd\Signature\OutputListDocumentTypeData;
use App\Data\Hrd\Signature\TemplateListData;
use App\Data\Hrd\Signature\UpdateDocumentTypeData;
use App\Data\Hrd\Signatured\DocumentVersionListData;
use App\Enums\Hrd\Signature\Template\DocumentFileStatus;
use App\Exceptions\DataNotFound;
use App\Exceptions\DetectPlaceholderFailed;
use App\Exceptions\FailedToUploadFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Company\Repository\DivisionRepository;
use Modules\Company\Repository\PositionRepository;
use Modules\Hrd\Exceptions\DocumentTypeInUse;
use Modules\Hrd\Exceptions\TemplateStillHavePendingReview;
use Modules\Hrd\Repository\DocumentTypeRepository;
use Modules\Hrd\Repository\MasterDocumentRepository;
use PhpOffice\PhpWord\TemplateProcessor;

class SignatureService
{
    public function __construct(
        private readonly DocumentTypeRepository $documentTypeRepo,
        private readonly PositionRepository $positionRepo,
        private readonly DivisionRepository $divisionRepo,
        private readonly MasterDocumentRepository $masterDocumentRepo
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
                if (! in_array($variable, $availables) && ! Str::contains($variable, 'signature')) {
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
            if ($master->isHavePendingReview) {
                throw new TemplateStillHavePendingReview;
            }

            // Upload files
            $this->createFolder(config('signature.master_path'));
            $filename = 'document_master_'.$documentType->code.'_'.$master->current_active_version_text.'.'.$payload->file->getClientOriginalExtension();
            $storeFile = Storage::putFileAs(config('signature.master_path'), $payload->file, $filename);

            if (! $storeFile) {
                throw new FailedToUploadFile;
            }

            // Record files
            $master->files()->create([
                'path' => config('signature.master_path').'/'.$filename,
                'file_type' => $payload->file->getClientOriginalExtension(),
                'placeholder_mapping' => $payload->placeholders,
                'status' => DocumentFileStatus::PendingReview,
            ]);

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
                    'files:id,master_document_id,placeholder_mapping,version,status,created_at',
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
                        status: $file->status->value,
                        is_active: true,
                        placeholders: count($file->placeholder_mapping),
                        date: $file->created_at,
                        author: ! $file->author ? 'N/A' : $file->author?->employee?->nickname ?? $file->author->email
                    );
                }

                return new TemplateListData(
                    uid: $item->uid,
                    name: $item->name,
                    type: $item->documentType->code,
                    latest_version_label: $item->current_active_version_text,
                    updated_at: $item->updated_at,
                    active_version_label: $item->activeDocument ? $item->current_active_version_text : '',
                    active_version_status: $item->activeDocument ? 'Active' : '',
                    active_version_status_color: '',
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
}
