<?php

namespace App\Actions\Project\Entertainment;

use App\Exceptions\UploadImageFailed;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Models\EntertainmentTaskSongResultImage;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Modules\Production\Repository\EntertainmentTaskSongRepository;
use Modules\Production\Repository\EntertainmentTaskSongResultRepository;

class ReportAsDone
{
    use AsAction;

    public function handle(
        array $payload,
        string $projectUid,
        string $songUid,
        $generalService)
    {
        $projectId = $generalService->getIdFromUid($projectUid, new Project);
        $songId = $generalService->getIdFromUid($songUid, new ProjectSongList);

        $images = [];
        if ((isset($payload['images'])) && (count($payload['images']) > 0)) {
            // upload image
            foreach ($payload['images'] as $image) {
                $image = $generalService->uploadImageandCompress(
                    path: "projects/{$projectId}/entertainment/song/{$songId}",
                    compressValue: 0,
                    image: $image
                );

                if ($image === false) {
                    throw new UploadImageFailed;
                }

                $images[] = new EntertainmentTaskSongResultImage(['path' => $image]);
            }
        }

        $taskRepo = new EntertainmentTaskSongRepository;
        $repo = new EntertainmentTaskSongResultRepository;

        $task = $taskRepo->show(
            uid: 'id',
            select: 'id,employee_id',
            where: "project_id = {$projectId} AND project_song_list_id = {$songId}"
        );

        $result = $repo->store([
            'task_id' => $task->id,
            'nas_path' => $payload['nas_path'],
            'employee_id' => $task->employee_id,
            'note' => $payload['note'] ?? null,
        ]);

        $result->images()->saveMany($images);
    }
}
