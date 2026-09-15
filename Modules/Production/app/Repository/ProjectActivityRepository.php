<?php

namespace Modules\Production\Repository;

use App\Repository\BaseRepository;
use Modules\Production\Models\ProjectActivity;

class ProjectActivityRepository extends BaseRepository
{
    public function __construct(ProjectActivity $model)
    {
        return parent::__construct($model);
    }
}
