<?php

namespace App\Actions\Project\Entertainment;

use App\Enums\Production\Entertainment\TaskSongLogType;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Repository\EntertainmentTaskSongLogRepository;

class StoreLogAction
{
    use AsAction;

    public function handle(string $type, array $payload, array $params = [])
    {
        $repo = new EntertainmentTaskSongLogRepository;

        // generate message based on type
        $text = TaskSongLogType::generateText($type, $payload);

        $data = collect($payload)->only(['project_song_list_id', 'entertainment_task_song_id', 'project_id', 'employee_id'])
            ->merge(['text' => $text])
            ->merge(['param_text' => $params])
            ->toArray();

        $repo->store($data);
    }
}
