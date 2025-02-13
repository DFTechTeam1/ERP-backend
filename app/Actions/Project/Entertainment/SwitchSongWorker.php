<?php

namespace App\Actions\Project\Entertainment;

use App\Enums\Production\Entertainment\TaskSongLogType;
use App\Services\GeneralService;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Repository\EntertainmentTaskSongRepository;
use Modules\Production\Repository\ProjectSongListRepository;

class SwitchSongWorker
{
    use AsAction;

    public function handle(string $nextWorkerUid, string $songUid)
    {
        DB::beginTransaction();
        try {
            $repo = new ProjectSongListRepository();
            $employeeRepo = new EmployeeRepository();
            $taskRepo = new EntertainmentTaskSongRepository();
    
            // detach //
            // gather all information
            $song = $repo->show(
                uid: $songUid,
                select: 'id,name,project_id',
                relation: [
                    'task:id,project_song_list_id,employee_id',
                    'task.employee:id,nickname',
                    'project:id,uid'
                ]
            );
    
            $currentWorker = $song->task->employee->nickname;
    
            $author = $employeeRepo->show(
                uid: 'id',
                select: 'id,nickname',
                where: "user_id = " . auth()->id()
            );
    
            // action to remove
            $taskRepo->delete(
                id: $song->task->id,
            );
    
            // add to the log
            StoreLogAction::run(
                type: TaskSongLogType::RemoveWorkerFromTask->value,
                payload: [
                    'project_song_list_id' => $song->id,
                    'project_id' => $song->project_id,
                ],
                params: [
                    'pm' => $author->nickname,
                    'user' => $currentWorker
                ]
            );
    
            // attach new worker
            DistributeSong::run(
                [
                    'employee_uid' => $nextWorkerUid
                ],
                $song->project->uid,
                $songUid,
                new GeneralService
            );
            
            DB::commit();

            return [
                'error' => false
            ];
        } catch (\Throwable $th) {
            return [
                'error' => true,
                'message' => $th
            ];
        }
    }
}
