<?php

namespace Modules\Finance\Repository;

use App\Repository\BaseRepository;
use Modules\Finance\Models\DfEngineFeature;

class DfEngineFeatureRepository extends BaseRepository
{
    public function __construct(DfEngineFeature $model)
    {
        return parent::__construct($model);
    }
}
