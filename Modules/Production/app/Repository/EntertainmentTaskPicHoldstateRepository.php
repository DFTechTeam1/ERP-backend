<?php

namespace Modules\Production\Repository;

use App\Repository\BaseRepository;
use Modules\Production\Models\EntertainmentTaskPicHoldstate;

class EntertainmentTaskPicHoldstateRepository extends BaseRepository
{
    public function __construct(EntertainmentTaskPicHoldstate $model)
    {
        return parent::__construct($model);
    }
}
