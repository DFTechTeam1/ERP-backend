<?php

namespace Modules\Production\Services;

use App\Data\Entertainment\BulkCreateSongData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Production\Models\Project;
use Modules\Production\Repository\ProjectRepository;

class EntertainmentService
{
    public function __construct(
        private readonly ProjectRepository $projectRepo
    ) {}

    protected function getProject(string $projectUid): Project|Collection
    {
        $project = $this->projectRepo->show(uid: $projectUid, select: 'id,uid,name,status');

        if (! $project) {
            throw new ModelNotFoundException('Project is not found.');
        }

        return $project;
    }

    public function createSong(BulkCreateSongData $payload, string $projectUid): array
    {
        try {
            $project = $this->getProject($projectUid);


            return generalResponse(
                message: "Success",
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
