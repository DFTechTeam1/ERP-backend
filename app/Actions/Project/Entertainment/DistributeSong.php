<?php

namespace App\Actions\Project\Entertainment;

use App\Enums\Production\Entertainment\TaskSongLogType;
use App\Enums\Production\TaskSongStatus;
use App\Services\GeneralService;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Jobs\DistributeSongJob;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Modules\Production\Repository\EntertainmentTaskSongRepository;

class DistributeSong
{
    use AsAction;

    public function handle(array $payload, string $projectUid, string $songUid, GeneralService $generalService)
    {
        $employeeId = $generalService->getIdFromUid($payload['employee_uid'], new Employee());
        $songId = $generalService->getIdFromUid($songUid, new ProjectSongList());
        $projectId = $generalService->getIdFromUid($projectUid, new Project());

        $entertainmentTaskSongRepo = new EntertainmentTaskSongRepository();
        $employeeRepo = new EmployeeRepository();

        $taskSong = $entertainmentTaskSongRepo->store([
            'project_song_list_id' => $songId,
            'status' => TaskSongStatus::Active->value,
            'employee_id' => $employeeId,
            'project_id' => $projectId,
            'givenBy' => '',
            'target' => ''
        ]);

        // store log
        $employee = $employeeRepo->show(
            uid: $payload['employee_uid'],
            select: 'id,nickname'
        );

        $user = $employeeRepo->show(
            uid: 'id',
            select: 'id,nickname',
            where: "user_id = " . auth()->id()
        );

        // logging task
        StoreLogAction::run(
            type: TaskSongLogType::AssignJob->value,
            payload: [
                'project_song_list_id' => $songId,
                'entertainment_task_song_id' => $taskSong->id,
                'project_id' => $projectId,
                'employee_id' => null,
            ],
            params: [
                'givenBy' => $user->nickname,
                'target' => $employee->nickname
            ]
        );

        DistributeSongJob::dispatch($payload['employee_uid'], $projectUid, $songUid)->afterCommit();
    }
}
