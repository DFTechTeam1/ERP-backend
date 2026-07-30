<?php

namespace Modules\Production\Repository;

use App\Repository\BaseRepository;
use Modules\Production\Models\ProjectDealChange;

class ProjectDealChangeRepository extends BaseRepository
{
    public function __construct(ProjectDealChange $model)
    {
        return parent::__construct($model);
    }
}