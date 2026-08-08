<?php

namespace Modules\Production\Repository;

use App\Repository\BaseRepository;
use Modules\Production\Models\ProjectSongItem;

class ProjectSongItemRepository extends BaseRepository
{
    public function __construct(ProjectSongItem $model)
    {
        return parent::__construct($model);
    }

    public function deleteWhere(array $where): bool
    {
        $data = $this->show([
            'where' => $where,
            'select' => ['id'],
        ]);

        if (! $data) {
            return false;
        }

        return $this->delete($data);
    }
}
