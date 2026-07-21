<?php

namespace Modules\Hrd\Jobs;

use App\Services\RealtimeNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Hrd\Repository\EmployeeRepository;

class GenerateDocumentNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        /** @var array<int, int> */
        private readonly array $employeeIds
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $employees = app(EmployeeRepository::class)->list(
            select: 'id,name,position_id',
            whereIn: [
                'key' => 'id',
                'value' => $this->employeeIds,
            ],
            relation: [
                'position:id,division_id',
            ]
        );

        $service = app(RealtimeNotificationService::class);

        foreach ($employees as $employee) {
            $title = __('notification.documentSigningTitle');
            $message = __('notification.documentSigningMessage');
            $service->send(
                recipients: $employee,
                topic: RealtimeNotificationService::TOPIC_GENERAL,
                payload: [
                    'title' => $title,
                    'message' => $message,
                    'icon' => '📝',
                    'action' => 'document_ready_to_sign',
                ],
            );
        }
    }
}
