<?php

namespace Modules\Hrd\Repository;

use App\Repository\BaseRepository;
use Modules\Hrd\Models\MasterDocumentSigner;

class MasterDocumentSignerRepository extends BaseRepository
{
    public function __construct(MasterDocumentSigner $model)
    {
        return parent::__construct($model);
    }
}
