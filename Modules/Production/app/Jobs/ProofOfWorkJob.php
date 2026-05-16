<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Email\Services\WhatsappService;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\WhatsappGroup;

class ProofOfWorkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $payload;

    public $projectId;

    public $taskId;

    public $taskPic;

    public WhatsappService $whatsappService;

    /**
     * Create a new job instance.
     */
    public function __construct(int $projectId, int $taskId, int $taskPic)
    {

        $this->projectId = $projectId;

        $this->taskId = $taskId;

        $this->taskPic = $taskPic;

        $this->whatsappService = new WhatsappService;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // get project manager
        $pm = \Modules\Production\Models\ProjectPersonInCharge::selectRaw('id,project_id,pic_id')
            ->with([
                'employee:id,user_id,nickname,user_id,phone',
            ])
            ->where('project_id', $this->projectId)
            ->get();

        // get task pic
        $taskPic = \Modules\Hrd\Models\Employee::where('user_id', $this->taskPic)->first();

        // get detail task
        $task = \Modules\Production\Models\ProjectTask::selectRaw('id,name,uid,project_id')
            ->with([
                'project:id,name,uid',
                'pics:id,project_task_id,employee_id',
                'pics.employee:id,nickname',
            ])
            ->find($this->taskId);

        foreach ($pm as $manager) {
            $this->sendToWhatsapp(
                manager: $manager->employee,
                taskName: $task->name,
                projectName: $task->project->name,
                taskPicName: $taskPic->nickname
            );

            \App\Services\NotificationService::send(
                recipients: $manager->employee,
                action: 'task_has_been_hold_by_user',
                data: [
                    'parameter1' => $manager->employee->nickname,
                    'parameter2' => $task->name,
                    'parameter3' => $task->project->name,
                    'parameter4' => $taskPic->nickname,
                ],
                channels: ['database'],
                options: [
                    'url' => '/admin/production/project/'.$task->project->uid,
                    'database_type' => 'production',
                ]
            );

            // send pusher
            (new \App\Services\PusherNotification)->send(
                channel: 'my-channel-'.$manager->employee->user_id,
                event: 'new-db-notification',
                payload: [
                    'update' => true,
                ],
            );
        }
    }

    public function sendToWhatsapp(
        Employee $manager,
        string $taskName,
        string $projectName,
        string $taskPicName
    ) {
        $whatsappGroup = WhatsappGroup::where('employee_id', $manager->id)
            ->first();

        if ($whatsappGroup) {
            $payload = [
                'to' => $whatsappGroup->group_id,
                'message' => "{$taskPicName} telah menyelesaikan task {$taskName} di event {$projectName}. Kamu sudah bisa mulai check ya.",
                'isGroup' => true,
                'mentions' => ["62{$manager->phone}"],
                'actionType' => 'new-assignment-task',
            ];

            $this->whatsappService->sendWhatsappMessage($payload);
        }
    }
}
