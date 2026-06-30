<?php

namespace Modules\Production\Repository;

use App\Repository\BaseRepository;
use Modules\Production\Models\EntertainmentTaskDeadlineHistory;

class EntertainmentTaskDeadlineHistoryRepository extends BaseRepository
{
    public function __construct(EntertainmentTaskDeadlineHistory $model)
    {
        return parent::__construct($model);
    }
}
