<?php

namespace Modules\Hrd\Repository;

use App\Repository\BaseRepository;
use Modules\Hrd\Models\EmploymentStatus;

class EmploymentStatusRepository extends BaseRepository
{
    public function __construct(EmploymentStatus $model)
    {
        return parent::__construct($model);
    }
}
