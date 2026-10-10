<?php

namespace Modules\Finance\Repository;

use App\Repository\BaseRepository;
use Modules\Finance\Models\DfEngineApiKey;

class DfEngineApiKeyRepository extends BaseRepository
{
    public function __construct(DfEngineApiKey $model)
    {
        return parent::__construct($model);
    }
}
