<?php

namespace Modules\Hrd\Services;

use App\Data\Hrd\Signature\BulkCreateDocumentTypeData;
use App\Data\Hrd\Signature\BulkDeleteDocumentTypeData;
use App\Data\Hrd\Signature\BulkUpdateDocumentTypeData;
use App\Data\Hrd\Signature\BulkUpdateDocumentTypeItemData;
use App\Data\Hrd\Signature\CreateDocumentTypeData;
use App\Data\Hrd\Signature\DefaultSignerItemData;
use App\Data\Hrd\Signature\DetectPlaceholderData;
use App\Data\Hrd\Signature\ListDocumentTypeSignerData;
use App\Data\Hrd\Signature\UpdateDocumentTypeData;
use App\Data\Hrd\Signature\ListDocumentTypeData;
use App\Data\Hrd\Signature\OutputListDocumentTypeData;
use App\Exceptions\DataNotFound;
use App\Exceptions\DetectPlaceholderFailed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Company\Repository\DivisionRepository;
use Modules\Company\Repository\PositionRepository;
use Modules\Hrd\Repository\DocumentTypeRepository;
use PhpOffice\PhpWord\TemplateProcessor;

class SignatureService
{
    public function __construct(
        private readonly DocumentTypeRepository $documentTypeRepo,
        private readonly PositionRepository $positionRepo,
        private readonly DivisionRepository $divisionRepo
    )
    {
    }

    /**
     * Parsing document type payload
     *
     * @param CreateDocumentTypeData|UpdateDocumentTypeData $payload
     * @return array
     */
    protected function parseDocumentTypePayload(CreateDocumentTypeData|UpdateDocumentTypeData $payload): array
    {
        $formattedDivisions = [];
        foreach ($payload->default_signers as $signer) {
            $division = $this->divisionRepo->show(uid: $signer->division_id, select: 'id');
            $formattedDivisions[] = [
                'division_id' => $division->id,
                'order' => $signer->order
            ];
        }

        return [
            'category' => $payload->category,
            'name' => $payload->name,
            'code' => $payload->code,
            'retention' => $payload->retention_years,
            'default_number_of_signers' => count($payload->default_signers),
            'status' => $payload->is_active ?? true,
            'signers' => $formattedDivisions
        ];
    }

    /**
     * Fetch list of document types
     *
     * @return array
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
                    'signers.division:id,uid,name'
                ]
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
                    uid: (string)$item->id,
                    code: $item->code,
                    category: $item->category->label(),
                    category_color: $item->category->color(),
                    retention_years: $item->retention,
                    default_signers: $item->default_number_of_signers,
                    is_have_active_template: true,
                    is_active: (bool)$item->status,
                    default_signer_items: $divisions
                );
            })->all();

            $totalData = $this->documentTypeRepo->get([
                'select' => 'id'
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
     *
     * @param CreateDocumentTypeData $payload
     * @return array
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
     *
     * @param BulkCreateDocumentTypeData $payload
     * @return array
     */
    public function bulkCreateDocumentType(BulkCreateDocumentTypeData $payload): array
    {
        \Illuminate\Support\Facades\DB::beginTransaction();

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

            \Illuminate\Support\Facades\DB::commit();
            return generalResponse(message: __('notification.successCreateDocumentType'));
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\DB::rollBack();
            return errorResponse($th);
        }
    }

    /**
     * Bulk delete document types
     *
     * @param BulkDeleteDocumentTypeData $uids
     * @return array
     */
    public function bulkDeleteDocumentType(BulkDeleteDocumentTypeData $uids): array
    {
        try {
            // TODO: Validation each uid. Check to document template relation

            $this->documentTypeRepo->bulkDelete($uids->uids);

            return generalResponse(message: __('notification.successDeleteDocumentType'));
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update current document type
     *
     * @param UpdateDocumentTypeData $request
     * @param string $documentId
     * @return array
     */
    public function updateDocumentType(\App\Data\hrd\Signature\UpdateDocumentTypeData $request, string $documentId): array
    {
        DB::beginTransaction();
        try {
            $documentType = $this->documentTypeRepo->show([
                'where' => ['id' => $documentId],
                'select' => ['id']
            ]);

            if (!$documentType) {
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
     * @param BulkUpdateDocumentTypeData $payload
     * @return array
     */
    public function bulkEditDocumentType(BulkUpdateDocumentTypeData $payload): array
    {
        DB::beginTransaction();
        try {
            // mapping payload
            $payloadUpdate = [];
            if ($payload->changes->category) $payloadUpdate['category'] = $payload->changes->category;
            if (gettype($payload->changes->is_active) === 'boolean') $payloadUpdate['status'] = $payload->changes->is_active;
            if ($payload->changes->retention_years) $payloadUpdate['retention'] = $payload->changes->retention_years;

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

    protected function createTmpFolder(): void
    {
        if (Storage::disk('public')->directoryExists('signature/tmp')) {
            Storage::disk('public')->makeDirectory('signature/tmp');
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
                if (! in_array($variable, $availables) && ! \Illuminate\Support\Str::contains($variable, 'signature')) {
                    $missingKeys[] = $variable;
                }
            }

            return [
                'error' => false,
                'data' => [
                    'availables' => $availables,
                    'variables' => $variables,
                    'missing' => $missingKeys
                ]
            ];
        } catch (\Throwable $th) {
            return [
                'error' => true
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
     *
     * @param DetectPlaceholderData $payload
     * @return array
     */
    public function detectPlaceholder(DetectPlaceholderData $payload): array
    {
        try {
            $file = $payload->file;

            $type = $this->documentTypeRepo->show([
                'where' => ['id' => $payload->documentTypeId],
                'select' => ['id'],
                'with' => [
                    'signers:id,type_id'
                ]
            ]);

            // upload to tmp folder first
            $this->createTmpFolder();

            $filename = 'tmp_file' . strtotime('now') . rand(100, 999) . '.' . $file->getClientOriginalExtension();
            Storage::disk('public')
                ->putFileAs('signature/tmp', $file, $filename);

            $placeholders = $this->breakdownPlaceholder(storage_path('app/public/signature/tmp/' . $filename));

            if ($placeholders['error']) {
                throw new DetectPlaceholderFailed();
            }

            $isSignatureEquals = (bool) $type->signers->count() == count($placeholders['data']['variables']);

            $merged = array_merge($placeholders['data'], ['is_signature_equals' => $isSignatureEquals]);

            $merged['can_continue'] = (bool) count($merged['missing']) == 0 && $isSignatureEquals;

            // Delete tmp file
            Storage::disk('public')->delete('signature/tmp/' . $filename);

            return generalResponse(
                message: "Success",
                data: $merged
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
