<?php

namespace App\Actions\Project;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Repository\ProjectRepository;

class DetailProject
{
    use AsAction;

    public function handle(string $uid, ProjectRepository $repo)
    {
        $projectId = getIdFromUid($uid, new \Modules\Production\Models\Project());
        $output = getCache('detailProject' . $projectId);

        if (!$output) {
            $data = $repo->show($uid, '*', [
                'marketing:id,name,employee_id',
                'personInCharges:id,pic_id,project_id',
                'personInCharges.employee:id,name,employee_id,uid,boss_id',
                'references:id,project_id,media_path,name,type',
                'equipments.inventory:id,name',
                'equipments.inventory.image',
                'marketings:id,marketing_id,project_id',
                'marketings.marketing:id,name',
                'country:id,name',
                'state:id,name',
                'city:id,name',
                'projectClass:id,name,maximal_point',
            ]);

            $progress = FormatProjectProgress::run($data->tasks, $projectId);

            $eventTypes = \App\Enums\Production\EventType::cases();
            $classes = \App\Enums\Production\Classification::cases();

            // get teams
            $projectTeams = GetProjectTeams::run($data);
            $teams = $projectTeams['teams'];
            $pics = $projectTeams['pics'];
            $picIds = $projectTeams['picUids'];

            $marketing = $data->marketing ? $data->marketing->name : '-';

            $eventType = '-';
            foreach ($eventTypes as $et) {
                if ($et->value == $data->event_type) {
                    $eventType = $et->label();
                }
            }

            $eventClass = '-';
            $eventClassColor = null;
            foreach ($classes as $class) {
                if ($class->value == $data->classification) {
                    $eventClass = $class->label();
                    $eventClassColor = $class->color();
                }
            }

            $boardsData = FormatBoards::run($uid);

            $equipments = FormatEquipment::run($data->id);

            // days to go
            $projectEndDate = Carbon::parse($data->project_date);
            $nowTime = Carbon::now();
            $daysToGo = floor($nowTime->diffInDays($projectEndDate));

            // check time to upload showreels
            $allowedUploadShowreels = true;
            $currentTasks = [];
            foreach ($boardsData as $board) {
                foreach ($board['tasks'] as $task) {
                    $currentTasks[] = $task;
                }
            }
            $currentTaskStatusses = collect($currentTasks)->pluck('status')->count();
            $completedStatus = collect($currentTasks)->filter(function ($filter) {
                return $filter['status'] == \App\Enums\Production\TaskStatus::Completed->value;
            })->values()->count();
            // if ($currentTaskStatusses == $completedStatus) {
            //     $allowedUploadShowreels = true;
            // }

            $output = [
                'id' => $data->id,
                'allowed_upload_showreels' => $allowedUploadShowreels,
                'uid' => $data->uid,
                'name' => $data->name,
                'country_id' => $data->country_id,
                'state_id' => $data->state_id,
                'city_id' => $data->city_id,
                'feedback' => $data->feedback,
                'event_type' => $eventType,
                'event_type_raw' => $data->event_type,
                'event_class_raw' => $data->project_class_id,
                'event_class' => $data->projectClass->name,
                'event_class_color' => $eventClassColor,
                'project_date' => date('d F Y', strtotime($data->project_date)),
                'days_to_go' => $daysToGo,
                'venue' => $data->venue,
                'city_name' => $data->city_name,
                'marketing' => $marketing,
                'pic' => implode(', ', $pics),
                'pic_ids' => $picIds,
                'collaboration' => $data->collaboration,
                'note' => $data->note ?? '-',
                'led_area' => $data->led_area,
                'led_detail' => json_decode($data->led_detail, true),
                'client_portal' => $data->client_portal,
                'status' => $data->status_text,
                'status_color' => $data->status_color,
                'status_raw' => $data->status,
                'references' => FormatReferenceFile::run($data->references, $data->id),
                'boards' => $boardsData,
                'teams' => $teams,
                'task_type' => $data->task_type,
                'task_type_text' => $data->task_type_text,
                'task_type_color' => $data->task_type_color,
                'progress' => $progress,
                'equipments' => $equipments,
                'showreels' => $data->showreels_path,
                'person_in_charges' => $data->personInCharges,
                'project_maximal_point' => $data->projectClass->maximal_point,
            ];

            storeCache('detailProject' . $data->id, $output);
        }

        $output = FormatTaskPermission::run($output, $projectId);

        return $output;
    }
}
