<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Email\Services\WhatsappService;
use Modules\Hrd\Models\WhatsappGroup;
use Modules\Production\Models\Project;

class UpdatePicProjectLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private WhatsappService $whatsappService;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private array $payload
    )
    {
        $this->whatsappService = new WhatsappService();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $currentPicWhatsappGroup = WhatsappGroup::selectRaw('id,group_id')
            ->where('employee_id', $this->payload['current_pic_id'])
            ->first();
        $newPicWhatsappGroup = WhatsappGroup::selectRaw('id,group_id,employee_id')
            ->with([
                'employee:id,name'
            ])
            ->where('employee_id', $this->payload['new_pic_id'])
            ->first();
        $projectName = $this->payload['project_name'];

        if ($currentPicWhatsappGroup) {
            $payload = [
                'to' => $currentPicWhatsappGroup->group_id,
                'message' => "Halo All. Event {$projectName} sudah di alihkan ke tim {$newPicWhatsappGroup->employee->name}",
                'isGroup' => true,
                'mentions' => [],
                'actionType' => 'update-pic-project',
            ];

            $this->whatsappService->sendWhatsappMessage($payload);
        }

        if ($newPicWhatsappGroup) {
            $payloadNew = [
                'to' => $newPicWhatsappGroup->group_id,
                'message' => "Halo All. Event {$projectName} akan di kerjakan oleh tim mu. Persiapkan sebaik mungkin ya!",
                'isGroup' => true,
                'mentions' => [],
                'actionType' => 'assign-pic-project',
            ];

            $this->whatsappService->sendWhatsappMessage($payloadNew);
        }
    }
}
