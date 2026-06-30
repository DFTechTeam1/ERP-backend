<?php

namespace Modules\Production\Repository;

use App\Repository\BaseRepository;
use Modules\Production\Models\EntertainmentTaskPicApprovalstate;

class EntertainmentTaskPicApprovalstateRepository extends BaseRepository
{
    public function __construct(EntertainmentTaskPicApprovalstate $model)
    {
        return parent::__construct($model);
    }
}
