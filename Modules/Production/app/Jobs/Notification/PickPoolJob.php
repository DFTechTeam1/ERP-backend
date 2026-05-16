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

class PickPoolJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private WhatsappService $whatsappService;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private ProjectTask $task,
        private Project $project,
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
            foreach ($this->project->personInCharges as $pic) {
                if ($pic->whatsappGroupPic && $pic->whatsappGroupPic->group_id) {
                    $payload = [
                        'to' => $pic->whatsappGroupPic->group_id,
                        'message' => "{$this->actor->nickname} telah mengambil task {$this->task->name} di event {$this->project->name}",
                        'isGroup' => true,
                        'mentions' => [],
                        'actionType' => 'pick-task',
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
