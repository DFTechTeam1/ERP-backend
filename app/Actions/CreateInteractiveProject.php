<?php

namespace App\Actions;

use App\Enums\Production\ProjectStatus;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Jobs\NotifyNewInteractiveProject;

class CreateInteractiveProject
{
    use AsAction;

    public function handle(int $projectId, array $payload)
    {
        $project = (new \Modules\Production\Repository\ProjectRepository)
            ->show(uid: 'id', select: '*', where: "id = {$projectId}");

        // Create interactive project
        $interactive = (new \Modules\Production\Repository\InteractiveProjectRepository)
            ->store(data: [
                'name' => $project->name,
                'client_portal' => $project->client_portal,
                'parent_project' => $project->id,
                'project_date' => $project->project_date,
                'event_type' => $project->event_type,
                'venue' => $project->venue,
                'marketing_id' => $project->marketing_id,
                'collaboration' => $project->collaboration,
                'status' => ProjectStatus::Draft->value,
                'note' => $payload['interactive_note'] ?? $project->note,
                'led_area' => $payload['interactive_area'] ?? $project->led_area,
                'led_detail' => $payload['interactive_detail'] ?? $project->led_detail,
                'project_class_id' => $project->project_class_id,
            ]);

        // create project boards
        $interactive->boards()->createMany([
            [
                'name' => 'Asset 3D',
                'sort' => '1',
            ],
            [
                'name' => 'Compositing',
                'sort' => '2',
            ],
            [
                'name' => 'Finalize',
                'sort' => '3',
            ],
        ]);

        // NotifyNewInteractiveProject::dispatch($interactive);
    }
}