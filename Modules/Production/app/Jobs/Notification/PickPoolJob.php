<?php

namespace Modules\Production\Jobs\Notification;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Email\Services\WhatsappService;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Repository\ProjectRepository;

class PickPoolJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private WhatsappService $whatsappService;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private ProjectTask $task,
        private string $projectUid,
        private Employee $actor
    ) {
        $this->whatsappService = new WhatsappService;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $project = (new ProjectRepository)->show(
                uid: $this->projectUid,
                select: 'id,name',
                relation: [
                    'personInCharges:id,project_id,pic_id',
                    'personInCharges.employee:id,phone',
                    'personInCharges.employee.picWhatsappGroups' => function ($query) {
                        $query->selectRaw('id,employee_id,group_id')
                            ->whereNotNull('community_id');
                    }
                ]);

            if ($project->personInCharges->count() > 0) {
                foreach ($project->personInCharges as $pics) {
                    if ($pics->employee && $pics->employee->picWhatsappGroups->count() > 0) {
                        foreach ($pics->employee->picWhatsappGroups as $group) {
                            $payload = [
                                'to' => $group->group_id,
                                'message' => "{$this->actor->nickname} telah mengambil task {$this->task->name} di event {$project->name}",
                                'isGroup' => true,
                                'mentions' => [],
                                'actionType' => 'pick-task',
                            ];
                    
                            (new WhatsappService)->sendWhatsappMessage($payload);
                        }
                    }
                }
            }
        } catch (\Throwable $th) {
            $this->handleError($th);
        }
    }

    private function handleError(\Throwable $th)
    {
        logging('ERROR SENDING POOL TASK WHATSAPP NOTIFICATION', [$th]);
    }
}
