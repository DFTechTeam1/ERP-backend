<?php

namespace Modules\Finance\Services;

class AiCostService
{
    protected function getFilter(): array
    {
        return [
            'period' => request('period'), // this_month | last_month | this_year
            'start' => request('start'), // override period. (ISO date)
            'end' => request('end'), // override period. (ISO date)
            'featureUid' => request('feature_uid'),
            'menuUid' => request('menu_uid'),
            'apiKeyUid' => request('api_key_uid'),
            'actorId' => request('actor_id')
        ];
    }

    public function summary()
    {
        try {
            $filter = $this->getFilter();
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}
