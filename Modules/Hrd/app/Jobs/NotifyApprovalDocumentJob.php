<?php

namespace Modules\Hrd\Jobs;

use App\Enums\Hrd\Signature\Template\DocumentFileStatus;
use App\Models\User;
use App\Services\RealtimeNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Hrd\Models\MasterDocumentFile;

class NotifyApprovalDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private MasterDocumentFile $version
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $service = app(RealtimeNotificationService::class);

        $employee = User::select(['id', 'email'])
            ->find($this->version->created_by);

        logging('employee', $employee ? $employee->toArray() : []);

        if (! $employee) {
            return;
        }

        $masterDocument = $this->version->masterDocument;
        $isRejected = $this->version->status === DocumentFileStatus::Rejected;

        $replace = [
            'name' => $masterDocument?->name,
            'version' => $this->version->version,
        ];

        $service->send(
            recipients: $employee,
            topic: RealtimeNotificationService::TOPIC_GENERAL,
            payload: [
                'title' => __($isRejected
                    ? 'notification.documentTemplateRejectedTitle'
                    : 'notification.documentTemplateApprovedTitle'),
                'message' => $this->buildMessage($isRejected, $replace),
                'icon' => $isRejected ? '❌' : '✅',
                'url' => '',
                'action' => $isRejected ? 'document_template_rejected' : 'document_template_approved',
                'data' => [
                    'master_document_uid' => $masterDocument?->uid,
                    'version' => $this->version->version,
                    'reason' => $isRejected ? $this->version->approval_note : null,
                ],
            ],
        );
    }

    /**
     * @param  array{name: ?string, version: ?string}  $replace
     */
    private function buildMessage(bool $isRejected, array $replace): string
    {
        if (! $isRejected) {
            return __('notification.documentTemplateApprovedMessage', $replace);
        }

        $reason = $this->version->approval_note;

        if (! $reason) {
            return __('notification.documentTemplateRejectedMessage', $replace);
        }

        return __('notification.documentTemplateRejectedMessageWithReason', $replace + ['reason' => $reason]);
    }
}
