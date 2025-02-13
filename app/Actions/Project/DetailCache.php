<?php

namespace App\Actions\Project;

use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Models\Project;
use Modules\Production\Repository\ProjectRepository;

class DetailCache
{
    use AsAction;

    public function handle(string $projectUid)
    {
        // get current data
        $projectId = getIdFromUid($projectUid, new Project());
        $currentData = getCache('detailProject' . $projectId);
        
        if (!$currentData) {
            DetailProject::run($projectUid, new ProjectRepository);
            $currentData = getCache('detailProject' . $projectId);
        }

        $currentData = FormatTaskPermission::run($currentData, $projectId);

        return $currentData;
    }
}
