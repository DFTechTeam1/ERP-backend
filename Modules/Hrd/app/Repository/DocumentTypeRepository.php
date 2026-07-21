<?php

namespace Modules\Hrd\Repository;

use App\Repository\BaseRepository;
use Modules\Hrd\Models\DocumentType;
use Modules\Hrd\Models\DocumentTypeSigner;

class DocumentTypeRepository extends BaseRepository
{
    public function __construct(DocumentType $model)
    {
        return parent::__construct($model);
    }

    public function bulkCreate(array $data)
    {
        return $this->query()
            ->insert($data);
    }

    public function bulkDelete(array $uids)
    {
        return $this->query()
            ->whereIn('id', $uids)
            ->delete();
    }

    public function bulkUpdate(array $payload, array $ids)
    {
        return $this->query()
            ->whereIn('id', $ids)
            ->update($payload);
    }

    /**
     * Assign signers to document type
     *
     * @param array $payload
     * @param DocumentType $type
     */
    public function assignSigners(array $payload, DocumentType $type): void
    {
        // Delete signers
        $deleteSigners = $type->signers->pluck('division_id')
            ->diff(collect($payload)->pluck('division_id')->toArray())
            ->toArray();

        DocumentTypeSigner::where('type_id', $type->id)
            ->whereIn('division_id', $deleteSigners)
            ->delete();

        foreach ($payload as $signer) {
            $type->signers()->updateOrCreate(
                ['division_id' => $signer['division_id']],
                ['order' => $signer['order']]
            );
        }
    }
}
