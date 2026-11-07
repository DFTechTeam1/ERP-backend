<?php

namespace Modules\Production\Services;

use App\Actions\Project\Entertainment\DefineSongAction;
use App\Data\Entertainment\BulkCreateSongData;
use App\Data\Entertainment\SongListData;
use App\Data\Entertainment\UpdateSongData;
use App\Enums\Production\ProjectStatus;
use App\Models\User;
use App\Repository\UserRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Repository\ProjectSongListRepository;
use Ramsey\Uuid\Uuid;

class EntertainmentService
{
    public function __construct(
        private readonly ProjectRepository $projectRepo,
        private readonly ProjectSongListRepository $songRepo,
        private readonly UserRepository $userRepo
    ) {}

    protected function defaultSongRelation()
    {
        return [
            'task:id,project_song_list_id,employee_id,project_id,status,time_tracker',
            'task.employee:id,uid,employee_id,name',
        ];
    }

    protected function getProject(string $projectUid, bool $validateForAction = false): Project|Collection
    {
        $relation = $this->defaultSongRelation();
        $relation = array_map(function ($item) {
            return 'songs.' . $item;
        }, $relation);

        $project = $this->projectRepo->show(
            uid: $projectUid,
            select: 'id,uid,name,status',
            relation: [
                'songs:id,uid,project_id,name,song_group,reason',
                ...$relation
            ]
        );

        if (! $project) {
            throw new ModelNotFoundException('Project is not found.');
        }

        if ($validateForAction && $project->status != ProjectStatus::OnGoing->value) {
            throw new Exception(message: "Cannot do any action if project is not started yet.", code: 403);
        }

        return $project;
    }

    protected function formatSong(ProjectSongList|Collection $song, User $user): SongListData
    {
        $actionList = DefineSongAction::run($song, $user);
        return new SongListData(
            ...$actionList,
            uid: $song->uid,
            name: $song->name,
            group: $song->song_group,
            status: '',
            status_text: '',
            status_color: '',
            status_request: null,
            my_own: false,
            formatted_created_at: Carbon::parse($song->created_at)->format('d F Y'),
            task: null,
        );
    }

    protected function getActor(): User
    {
        return $this->userRepo->detail(
            id: Auth::id(),
            select: 'id,email,employee_id'
        );
    }

    public function detail(string $projectUid, string $songUid): array
    {
        try {
            $project = $this->getProject($projectUid);

            $song = $this->songRepo->show(relation: $this->defaultSongRelation(), uid: '', where: "project_id = {$project->id} and uid = '{$songUid}'");

            $output = $this->formatSong($song, $this->getActor());

            return generalResponse(
                message: "Success",
                data: $output->toArray()
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function list(string $projectUid): array
    {
        try {
            $project = $this->getProject($projectUid);

            $user = $this->getActor();

            /** @var array<int, SongListData> */
            $output = $project->songs->map(function ($song) use ($user) {
                return $this->formatSong($song, $user);
            })->toArray();

            return generalResponse(
                message: "Success",
                data: $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function delete(string $projectUid, string $songUid): array
    {
        try {
            $song = $this->songRepo->show(uid: $songUid, select: 'id,name', relation: ['task:id,project_song_list_id']);

            if (! $song) {
                throw new ModelNotFoundException('Song is not found.');
            }

            if ($song->task) {
                throw new Exception('Cannot delete song that already have a task.');
            }

            $this->songRepo->delete(id: 0, where: "uid = '{$songUid}'");

            return generalResponse(
                message: "Success delete song"
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function update(UpdateSongData $payload, string $projectUid, string $songUid): array
    {
        try {
            $song = $this->songRepo->show(uid: $songUid, select: 'id,name');

            if (! $song) {
                throw new ModelNotFoundException('Song is not found.');
            }

            $this->songRepo->update(
                data: [
                    'name' => $payload->song,
                    'song_group' => $payload->group,
                ],
                id: $songUid
            );

            return generalResponse(
                message: "Success update song",
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function createSong(BulkCreateSongData $payload, string $projectUid): array
    {
        DB::beginTransaction();
        try {
            $project = $this->getProject(projectUid: $projectUid, validateForAction: true);
            $user = $this->getActor();

            $format = [];
            foreach ($payload->groups as $group) {
                foreach ($group->songs as $song) {
                    $format[] = [
                        'name' => $song,
                        'song_group' => $group->name,
                        'uid' => Uuid::uuid4()->toString(),
                        'created_by' => Auth::user()->employee_id,
                        'target_name' => null,
                        'reason' => null,
                    ];
                }
            }

            $project->songs()->createMany($format);

            DB::commit();

            return generalResponse(
                message: "Success",
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }
}
