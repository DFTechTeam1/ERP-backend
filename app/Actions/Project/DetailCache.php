<?php

namespace App\Actions\Project;

use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Models\Project;
use Modules\Production\Repository\EntertainmentTaskSongRepository;
use Modules\Production\Repository\ProjectRepository;

class DetailCache
{
    use AsAction;

    public function handle(string $projectUid, array $necessaryUpdate = [], bool $forceUpdateAll = false)
    {
        $projectId = getIdFromUid($projectUid, new Project);

        if ($forceUpdateAll) {
            clearCache('detailProject'.$projectId);
        }

        // get current data
        $currentData = getCache('detailProject'.$projectId);

        if (! $currentData) {
            DetailProject::run($projectUid, new ProjectRepository, new EntertainmentTaskSongRepository);
            $currentData = getCache('detailProject'.$projectId);
        }

        if (! empty($necessaryUpdate)) {
            foreach ($necessaryUpdate as $key => $value) {
                $currentData[$key] = $value;
            }
        }

        $currentData = FormatTaskPermission::run($currentData, $projectId);

        return $currentData;
    }
}
