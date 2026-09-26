<?php

namespace Modules\Finance\Repository;

use App\Repository\BaseRepository;
use Modules\Finance\Models\FiscalYear;

class FiscalYearRepository extends BaseRepository
{
    public function __construct(FiscalYear $model)
    {
        return parent::__construct($model);
    }
}
