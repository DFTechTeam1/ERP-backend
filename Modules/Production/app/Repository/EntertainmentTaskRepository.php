<?php

namespace Modules\Production\Repository;

use App\Repository\BaseRepository;
use Modules\Production\Models\EntertainmentTask;

class EntertainmentTaskRepository extends BaseRepository
{
    public function __construct(EntertainmentTask $model)
    {
        return parent::__construct($model);
    }
}
