<?php

namespace Modules\Hrd\Repository;

use App\Repository\BaseRepository;
use Modules\Hrd\Models\EmployeeWhatsappGroup;

class EmployeeWhatsappGroupRepository extends BaseRepository {
    public function __construct(EmployeeWhatsappGroup $model)
    {
        return parent::__construct($model);
    }
}