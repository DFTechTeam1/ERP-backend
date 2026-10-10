<?php

namespace Modules\Finance\Repository;

use App\Repository\BaseRepository;
use Modules\Finance\Models\DfEngineMenu;

class DfEngineMenuRepository extends BaseRepository
{
    public function __construct(DfEngineMenu $model)
    {
        return parent::__construct($model);
    }
}
