<?php

namespace Modules\Production\Jobs\Notification;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Email\Services\WhatsappService;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Repository\ProjectRepository;

class NewPoolTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private WhatsappService $whatsappService;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private bool $isUsingPic = false,
        private array $mentions = [],
        private ?ProjectTask $task = null,
        private string $projectUid = '',
        private string $boardName = ''
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
            
            $canSendMessage = true;
            $whatsappMessage = "Halo All. Ada task baru *{{taskname}}* ({$this->boardName}) di event {{eventname}} yang sudah siap diambil";
            if ($this->isUsingPic) {
                $whatsappMessage = "Halo, task {{taskname}} ({$this->boardName}) di event {{eventname}} sudah di assign ke kamu dan menunggu approval.\nLogin ERP untuk melanjutkan.";
            }
            $whatsappMessage = str_replace(
                ['{{taskname}}', '{{eventname}}'],
                [$this->task->name, $project->name],
                $whatsappMessage
            );

            $localMentions = [];
            if (! empty($this->mentions)) {
                $localMentions = collect($this->mentions)->map(function ($mention) {
                    return "62{$mention}";
                })->toArray();
            }

            if ($this->isUsingPic && empty($localMentions)) {
                $canSendMessage = false;
            }

            if ($project->personInCharges->count() > 0) {
                foreach ($project->personInCharges as $pics) {
                    if ($pics->employee && $pics->employee->picWhatsappGroups->count() > 0) {
                        foreach ($pics->employee->picWhatsappGroups as $group) {
                            $payload = [
                                'to' => $group->group_id,
                                'message' => $whatsappMessage,
                                'isGroup' => true,
                                'actionType' => 'new-assignment-task',
                                'mentions' => $localMentions
                            ];
                    
                            (new WhatsappService)->sendWhatsappMessage($payload);
                        }
                    }
                }
            }

            // foreach ($project->personInCharges as $picProject) {
            //     if ($picProject->whatsappGroupPic && $picProject->whatsappGroupPic->group_id && $canSendMessage) {
            //         $payload = [
            //             'to' => $picProject->whatsappGroupPic->group_id,
            //             'message' => $whatsappMessage,
            //             'isGroup' => true,
            //             'actionType' => 'new-assignment-task',
            //             'mentionAll' => true
            //         ];

            //         $this->whatsappService->sendWhatsappMessage($payload);
            //     }
            // }
        } catch (\Throwable $th) {
            $this->handleError($th);
        }
    }

    private function handleError(\Throwable $th)
    {
        logging('ERROR SENDING POOL TASK WHATSAPP NOTIFICATION', [$th]);
    }
}
