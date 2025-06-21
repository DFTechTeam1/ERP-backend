<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProofOfWorkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $payload;

    public $projectId;

    public $taskId;

    public $taskPic;

    /**
     * Create a new job instance.
     */
    public function __construct(int $projectId, int $taskId, int $taskPic)
    {

        $this->projectId = $projectId;

        $this->taskId = $taskId;

        $this->taskPic = $taskPic;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // get project manager
        $pm = \Modules\Production\Models\ProjectPersonInCharge::selectRaw('id,project_id,pic_id')
            ->where('project_id', $this->projectId)
            ->get();

        // detail project
        $project = \Modules\Production\Models\Project::find($this->projectId);

        // get task pic
        $taskPic = \Modules\Hrd\Models\Employee::where('user_id', $this->taskPic)->first();

        // get detail task
        $task = \Modules\Production\Models\ProjectTask::selectRaw('id,name,uid')
            ->with([
                'project:id,name,uid',
                'pics:id,project_task_id,employee_id',
                'pics.employee:id,nickname',
            ])
            ->find($this->taskId);

        foreach ($pm as $manager) {
            $employee = \Modules\Hrd\Models\Employee::find($manager->pic_id);
            $telegramChatId = $employee->telegram_chat_id;

            if ($telegramChatId) {
                \Illuminate\Support\Facades\Notification::send($employee, new \Modules\Production\Notifications\ProofOfWorkNotification($project, $taskPic, $task, [$telegramChatId]));
            }
        }
    }
}
