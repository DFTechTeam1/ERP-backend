<?php

namespace Modules\Hrd\Repository;

use App\Repository\BaseRepository;
use Modules\Hrd\Models\EmployeeSignature;

class EmployeeSignatureRepository extends BaseRepository
{
    public function __construct(EmployeeSignature $model)
    {
        return parent::__construct($model);
    }
}
