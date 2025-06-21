<?php

namespace App\Actions\Project;

use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Repository\ProjectBoardRepository;

class FormatProjectProgress
{
    use AsAction;

    public function handle(mixed $tasks, int $projectId)
    {
        $boardRepo = new ProjectBoardRepository;

        $grouping = [];

        foreach ($tasks as $task) {
            $grouping[$task['project_board_id']][] = $task;
        }

        $groupData = collect($tasks)->groupBy('project_board_id')->toArray();

        $projectBoards = $boardRepo->list('id,project_id,name,based_board_id', 'project_id = '.$projectId);

        $output = [];
        foreach ($projectBoards as $key => $board) {
            $output[$key] = $board;
            $output[$key]['total'] = 0;
            $output[$key]['completed'] = 0;
            $output[$key]['percentage'] = 0;
            $output[$key]['text'] = $board->name;

            if (count($groupData) > 0) {
                foreach ($groupData as $boardId => $value) {
                    if ($boardId == $board->id) {
                        $total = count($value);
                        $completed = collect($value)->where('status', '=', \App\Enums\Production\TaskStatus::Completed->value)->count();

                        $output[$key]['total'] = $total;
                        $output[$key]['completed'] = $completed;

                        $percentage = ceil($completed / $total * 100);
                        $output[$key]['percentage'] = $percentage;
                    }
                }
            }
        }

        return $output;
    }
}
