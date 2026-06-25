<?php

namespace Modules\Hrd\Repository;

use App\Enums\Whatsapp\GroupTargetType;
use App\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Modules\Hrd\Models\WhatsappGroup;

class WhatsappGroupRepository extends BaseRepository {
    public function __construct(WhatsappGroup $model)
    {
        return parent::__construct($model);
    }

    /**
     * Get all global whatsapp group
     *
     * @return Collection<int, WhatsappGroup>
     */
    public function getGlobalGroup(): Collection
    {
        return $this->query()
            ->where([
                'target_type' => GroupTargetType::All,
            ])
            ->with([
                'participants:id,group_id,employee_id'
            ])
            ->get();
    }

    /**
     * Get all groups under selected boss id
     *
     * @param integer $bossId
     * @return Collection<int, WhatsappGroup>
     */
    public function getBossWhatsappGroup(int $bossId): Collection
    {
        return $this->query()
            ->where([
                'target_type' => GroupTargetType::Team,
                'employee_id' => $bossId
            ])
            ->with([
                'participants:id,group_id,employee_id'
            ])
            ->get();
    }
}