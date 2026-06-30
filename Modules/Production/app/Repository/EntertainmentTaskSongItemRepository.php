<?php

namespace Modules\Production\Repository;

use App\Repository\BaseRepository;
use Modules\Production\Models\EntertainmentTaskSongItem;

class EntertainmentTaskSongItemRepository extends BaseRepository
{
    public function __construct(EntertainmentTaskSongItem $model)
    {
        return parent::__construct($model);
    }
}
