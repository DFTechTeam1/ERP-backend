<?php

namespace Modules\Finance\Repository;

use App\Repository\BaseRepository;
use Modules\Finance\Models\ProjectDealPriceChange;

class ProjectDealPriceChangeRepository extends BaseRepository
{
    public function __construct(ProjectDealPriceChange $model)
    {
        return parent::__construct($model);
    }
}
