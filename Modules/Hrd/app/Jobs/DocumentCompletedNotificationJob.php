<?php

namespace Modules\Hrd\Jobs;

use App\Services\RealtimeNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Hrd\Models\Employee;

class DocumentCompletedNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  int  $employeeId  Owner of the completed employee document
     * @param  string  $employeeDocumentUid  Uid of the completed document (for deep-linking)
     */
    public function __construct(
        private readonly int $employeeId,
        private readonly string $employeeDocumentUid,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $employee = Employee::find($this->employeeId);

        if (! $employee) {
            return;
        }

        app(RealtimeNotificationService::class)->send(
            recipients: $employee,
            topic: RealtimeNotificationService::TOPIC_GENERAL,
            payload: [
                'title' => __('notification.documentCompletedTitle'),
                'message' => __('notification.documentCompletedMessage'),
                'icon' => '✅',
                'action' => 'document_completed',
                'data' => [
                    'employee_document_uid' => $this->employeeDocumentUid,
                ],
            ],
        );
    }
}
