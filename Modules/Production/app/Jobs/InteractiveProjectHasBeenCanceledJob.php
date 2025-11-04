<?php

namespace Modules\Production\Jobs;

use App\Enums\System\BaseRole;
use App\Repository\UserRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Production\Notifications\InteractiveProjectHasBeenCanceledNotification;
use Modules\Production\Repository\InteractiveProjectRepository;

class InteractiveProjectHasBeenCanceledJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Authenticatable $user;

    protected string $interactiveUid;

    /**
     * Create a new job instance.
     */
    public function __construct(Authenticatable $user, string $interactiveUid)
    {
        $this->user = (new UserRepository)->detail(where: "id = {$user->id}", relation: ['employee']);
        $this->interactiveUid = $interactiveUid;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        // send notifications on all pic that have a task in this project, also notify director and sales and related project pm
        $project = (new InteractiveProjectRepository)->show(
            uid: $this->interactiveUid,
            select: 'id,parent_project,project_date,name',
            relation: [
                'canceledBy:id,employee_id',
                'canceledBy.employee:id,nickname',
                'parentProject:id,name',
                'tasks:intr_project_id,id',
                'tasks.pics:id,task_id,employee_id',
                'tasks.pics.employee:id,nickname,email,telegram_chat_id',
                'pics:id,intr_project_id,employee_id',
            ]
        );
        $projectPics = $project->pics->isNotEmpty() ? $project->pics->pluck('employee_id')->toArray() : [];

        $message = "Interactive Event {$project->name} has been canceled by {$this->user->employee->nickname}";

        foreach ($project->tasks as $task) {
            foreach ($task->pics as $pic) {
                if ($pic->employee) {
                    $pic->employee->notify(new InteractiveProjectHasBeenCanceledNotification($pic->employee, $message));
                }
            }
        }

        // notify director
        $whereUser = '';
        if (! empty($projectPics)) {
            $whereUser = 'employee_id NOT IN ('.implode(',', $projectPics).')';
        }
        $users = (new UserRepository)->list(
            select: 'id,employee_id',
            relation: [
                'employee:id,nickname,telegram_chat_id,email',
            ],
            whereRole: [BaseRole::Director->value],
            where: $whereUser
        );

        foreach ($users as $user) {
            if ($user->employee) {
                $user->employee->notify(new InteractiveProjectHasBeenCanceledNotification($user->employee, $message));
            }
        }
    }
}
