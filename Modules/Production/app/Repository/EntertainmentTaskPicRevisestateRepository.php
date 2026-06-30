<?php

namespace Modules\Production\Repository;

use App\Repository\BaseRepository;
use Modules\Production\Models\EntertainmentTaskPicRevisestate;

class EntertainmentTaskPicRevisestateRepository extends BaseRepository
{
    public function __construct(EntertainmentTaskPicRevisestate $model)
    {
        return parent::__construct($model);
    }
}
