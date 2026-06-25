<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Email\Services\WhatsappService;
use Modules\Production\Repository\ProjectRepository;

class ProjectClassChangedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private string $projectUid,
        private string $currentClassName,
        private string $nextClassName
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $project = (new ProjectRepository)->show(
            uid: $this->projectUid,
            select: 'id,name',
            relation: [
                'personInCharges:id,project_id,pic_id',
                'personInCharges.employee:id,phone',
                'personInCharges.employee.picWhatsappGroups:id,employee_id,group_id'
            ]);

        // Send message
        if ($project->personInCharges->count() > 0) {
            foreach ($project->personInCharges as $pics) {
                if ($pics->employee && $pics->employee->picWhatsappGroups->count() > 0) {
                    foreach ($pics->employee->picWhatsappGroups as $group) {
                        $payload = [
                            'to' => $group->group_id,
                            'message' => "Halo semua! Class event {$project->name} telah dirubah dari {$this->currentClassName} ke {$this->nextClassName}",
                            'isGroup' => true,
                            'actionType' => 'new-assignment-task',
                            'mentionAll' => true
                        ];
                
                        (new WhatsappService)->sendWhatsappMessage($payload);
                    }
                }
            }
        }
    }
}
