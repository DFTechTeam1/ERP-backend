<?php

namespace App\Actions\Project;

use App\Actions\DefineDetailProjectPermission;
use App\Enums\System\BaseRole;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Repository\EntertainmentTaskSongRepository;
use Modules\Production\Repository\ProjectRepository;

class DetailProject
{
    use AsAction;

    public function handle(string $uid, ProjectRepository $repo, EntertainmentTaskSongRepository $entertainmentTaskSongRepo)
    {
        $projectId = getIdFromUid($uid, new \Modules\Production\Models\Project);
        $output = getCache('detailProject'.$projectId);

        /**
         * Validate project for entertainment role
         */
        $user = auth()->user();
        $haveTask = true;
        $isVj = true;
        $isEntertainment = (bool) $user->hasRole(BaseRole::Entertainment->value) || $user->hasRole(BaseRole::ProjectManagerEntertainment->value);
        if ($user->hasRole(BaseRole::Entertainment->value)) {
            // check if project task, if user do not have a task in this project, throw error
            $user->load('employee');
            $task = $entertainmentTaskSongRepo->list(
                select: 'id',
                where: "employee_id = {$user->employee->id}"
            );

            if ($task->count() == 0) {
                $haveTask = false;
            }

            // check if user is a vj in this project
            $project = $repo->show(
                uid: $uid,
                select: 'id',
                relation: [
                    'vjs:id,project_id,employee_id',
                ]
            );
            if (! in_array($user->employee->id, collect($project->vjs)->pluck('employee_id')->toArray())) {
                $isVj = false;
            }

            if (! $isVj && ! $haveTask) {
                throw new AuthorizationException(message: "You're not allowed to access this page", code: 403);
            }
        }

        if (! $output) {
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
                'vjs:id,project_id,employee_id',
                'vjs.employee:id,nickname',
                'feedbacks:id,project_id,pic_id,feedback',
                'feedbacks.pic:id,name,avatar'
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
                'identifier_id' => $data->identifier_id,
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
                'vjs' => $data->vjs,
                'permission_list' => DefineDetailProjectPermission::run(),
                'is_entertainment' => $isEntertainment,
                'feedbacks' => $data->feedbacks,
                'is_my_feedback_exists' => $data->isMyFeedbackExists($user->employee_id)
            ];

            storeCache('detailProject'.$data->id, $output);
        }

        $output = FormatTaskPermission::run($output, $projectId);

        return $output;
    }
}
