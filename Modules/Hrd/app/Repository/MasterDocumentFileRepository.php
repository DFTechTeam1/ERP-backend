<?php

namespace Modules\Hrd\Repository;

use App\Repository\BaseRepository;
use Modules\Hrd\Models\MasterDocumentFile;

class MasterDocumentFileRepository extends BaseRepository
{
    public function __construct(MasterDocumentFile $model)
    {
        return parent::__construct($model);
    }
}
