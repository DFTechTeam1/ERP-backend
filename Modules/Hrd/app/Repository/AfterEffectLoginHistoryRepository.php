<?php

namespace Modules\Hrd\Repository;

use App\Repository\BaseRepository;
use Modules\Hrd\Models\AfterEffectLoginHistory;

class AfterEffectLoginHistoryRepository extends BaseRepository
{
    public function __construct(AfterEffectLoginHistory $model)
    {
        return parent::__construct($model);
    }
}
