<?php

namespace Modules\Production\Repository;

use App\Repository\BaseRepository;
use Modules\Production\Models\EntertainmentLog;

class EntertainmentLogRepository extends BaseRepository
{
    public function __construct(EntertainmentLog $model)
    {
        return parent::__construct($model);
    }
}
