<?php

namespace Modules\Hrd\Repository;

use App\Repository\BaseRepository;
use Modules\Hrd\Models\DocumentType;

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

    public function assignSigners()
    {
        
    }
}
