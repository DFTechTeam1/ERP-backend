<?php

namespace Modules\Hrd\Repository;

use App\Repository\BaseRepository;
use Modules\Hrd\Models\EmployeeSignatureTask;

class EmployeeSignatureTaskRepository extends BaseRepository
{
    public function __construct(EmployeeSignatureTask $model)
    {
        return parent::__construct($model);
    }
}
