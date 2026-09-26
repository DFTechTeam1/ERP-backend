<?php

namespace Modules\Hrd\Repository;

use App\Repository\BaseRepository;
use Modules\Finance\Models\AccountingPeriod;

class AccountingPeriodRepository extends BaseRepository
{
    public function __construct(AccountingPeriod $model)
    {
        return parent::__construct($model);
    }
}
