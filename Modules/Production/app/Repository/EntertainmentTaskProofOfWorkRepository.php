<?php

namespace Modules\Production\Repository;

use App\Repository\BaseRepository;
use Modules\Production\Models\EntertainmentTaskProofOfWork;

class EntertainmentTaskProofOfWorkRepository extends BaseRepository
{
    public function __construct(EntertainmentTaskProofOfWork $model)
    {
        return parent::__construct($model);
    }
}
