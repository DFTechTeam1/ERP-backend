<?php

namespace App\Schedules;

use App\Enums\Production\ProjectStatus;
use App\Jobs\PostNotifyCompleteProjectJob;

class PostNotifyCompleteProject
{
    public function __invoke()
    {
        $cutOffDate = '2024-11-01';
        $projects = \Modules\Production\Models\Project::selectRaw('id,project_date,name,status')
            ->with([
                'personInCharges:id,project_id,pic_id',
                'personInCharges.employee:id,uid,email,nickname,line_id,telegram_chat_id',
            ])
            ->whereIn(
                'status',
                [
                    ProjectStatus::OnGoing->value,
                    ProjectStatus::ReadyToGo->value,
                ]
            )
            ->whereBetween(
                'project_date',
                [
                    $cutOffDate,
                    date('Y-m-d', strtotime('-1 day')),
                ]
            )
            ->get();

        $outputData = [];
        foreach ($projects as $project) {
            foreach ($project->personInCharges as $personInCharge) {
                $employee = $personInCharge->employee;

                $outputData[] = [
                    'employee' => $employee,
                    'project' => $project,
                ];
            }
        }

        PostNotifyCompleteProjectJob::dispatch($outputData);
    }
}
