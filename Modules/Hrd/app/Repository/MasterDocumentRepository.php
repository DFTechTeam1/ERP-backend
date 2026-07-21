<?php

namespace Modules\Hrd\Repository;

use App\Repository\BaseRepository;
use Modules\Hrd\Models\MasterDocument;

class MasterDocumentRepository extends BaseRepository
{
    public function __construct(MasterDocument $model)
    {
        return parent::__construct($model);
    }
}
