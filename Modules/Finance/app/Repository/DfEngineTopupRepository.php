<?php

namespace Modules\Finance\Repository;

use App\Repository\BaseRepository;
use Modules\Finance\Models\DfEngineTopup;

class DfEngineTopupRepository extends BaseRepository
{
    public function __construct(DfEngineTopup $model)
    {
        return parent::__construct($model);
    }
}
