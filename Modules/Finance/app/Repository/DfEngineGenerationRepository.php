<?php

namespace Modules\Finance\Repository;

use App\Repository\BaseRepository;
use Modules\Finance\Models\DfEngineGeneration;

class DfEngineGenerationRepository extends BaseRepository
{
    public function __construct(DfEngineGeneration $model)
    {
        return parent::__construct($model);
    }
}
