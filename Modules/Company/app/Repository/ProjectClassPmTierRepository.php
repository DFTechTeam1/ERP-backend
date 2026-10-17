<?php

namespace Modules\Company\Repository;

use App\Repository\BaseRepository;
use Modules\Company\Models\ProjectClassPmTier;

class ProjectClassPmTierRepository extends BaseRepository
{
    public function __construct(ProjectClassPmTier $model)
    {
        return parent::__construct($model);
    }
}
