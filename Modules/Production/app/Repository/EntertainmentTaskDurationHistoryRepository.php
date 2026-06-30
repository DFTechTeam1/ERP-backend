<?php

namespace Modules\Production\Repository;

use App\Repository\BaseRepository;
use Modules\Production\Models\EntertainmentTaskDurationHistory;

class EntertainmentTaskDurationHistoryRepository extends BaseRepository
{
    public function __construct(EntertainmentTaskDurationHistory $model)
    {
        return parent::__construct($model);
    }
}
