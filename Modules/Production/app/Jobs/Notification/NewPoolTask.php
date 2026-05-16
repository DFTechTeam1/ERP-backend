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
        private ?Project $project = null,
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
            $whatsappMessage = "Halo All. Ada task baru *{{taskname}}* ({$this->boardName}) di event {{eventname}} yang sudah siap diambil";
            if ($this->isUsingPic) {
                $whatsappMessage = "Halo, task {{taskname}} ({$this->boardName}) di event {{eventname}} sudah di assign ke kamu dan menunggu approval.\nLogin ERP untuk melanjutkan.";
            }
            $whatsappMessage = str_replace(
                ['{{taskname}}', '{{eventname}}'],
                [$this->task->name, $this->project->name],
                $whatsappMessage
            );

            $localMentions = [];
            if (! empty($this->mentions)) {
                $localMentions = collect($this->mentions)->map(function ($mention) {
                    return "62{$mention}";
                })->toArray();
            }

            foreach ($this->project->personInCharges as $picProject) {
                if ($picProject->whatsappGroupPic && $picProject->whatsappGroupPic->group_id) {
                    $payload = [
                        'to' => $picProject->whatsappGroupPic->group_id,
                        'message' => $whatsappMessage,
                        'isGroup' => true,
                        'mentions' => $localMentions,
                        'actionType' => 'new-assignment-task',
                    ];

                    $this->whatsappService->sendWhatsappMessage($payload);
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
