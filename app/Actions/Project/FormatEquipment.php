<?php

namespace App\Actions\Project;

use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Repository\ProjectEquipmentRepository;

class FormatEquipment
{
    use AsAction;

    public function handle(int $projectId)
    {
        $projectEquipmentRepo = new ProjectEquipmentRepository;

        $equipments = $projectEquipmentRepo->list('*', 'project_id = '.$projectId, [
            'inventory:id,name',
            'inventory.image',
        ]);

        $equipments = collect((object) $equipments)->map(function ($item) {
            $canTakeAction = true;
            if (
                $item->status == \App\Enums\Production\RequestEquipmentStatus::Cancel->value ||
                $item->is_checked_pic ||
                $item->status == \App\Enums\Production\RequestEquipmentStatus::Decline->value ||
                $item->status == \App\Enums\Production\RequestEquipmentStatus::Return->value ||
                $item->status == \App\Enums\Production\RequestEquipmentStatus::CompleteAndNotReturn
            ) {
                $canTakeAction = false;
            }

            $item['is_cancel'] = $item->status == \App\Enums\Production\RequestEquipmentStatus::Cancel->value ? true : false;

            $item['can_take_action'] = $canTakeAction;

            return $item;
        })->all();

        return $equipments;
    }
}
