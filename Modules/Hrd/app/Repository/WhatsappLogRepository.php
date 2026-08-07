<?php

namespace Modules\Hrd\Repository;

use App\Repository\BaseRepository;
use Modules\Company\Models\WhatsappLog;

class WhatsappLogRepository extends BaseRepository {
    public function __construct(WhatsappLog $model)
    {
        return parent::__construct($model);
    }
}