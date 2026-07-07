<?php

namespace Modules\Hrd\Repository;

use App\Repository\BaseRepository;
use Modules\Hrd\Models\EmployeeDocument;

class EmployeeDocumentRepository extends BaseRepository
{
    public function __construct(EmployeeDocument $model)
    {
        return parent::__construct($model);
    }
}
