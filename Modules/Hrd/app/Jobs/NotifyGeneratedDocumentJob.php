<?php

namespace Modules\Hrd\Jobs;

use App\Enums\System\BaseRole;
use App\Models\User;
use App\Services\RealtimeNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Company\Jobs\SlackNotificationJob;
use Modules\Company\Notifications\SlackNotification;
use Modules\Hrd\Models\MasterDocument;
use Modules\Hrd\Models\MasterDocumentFile;

class NotifyGeneratedDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private MasterDocument $document
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $version = $this->document->pendingDocument;

        if (! $version) {
            return;
        }

        $directors = User::select(['id', 'email'])
            ->role(BaseRole::Director->value)
            ->get();

        if ($directors->isEmpty()) {
            return;
        }

        $service = app(RealtimeNotificationService::class);
        $message = $this->buildMessage($version);

        foreach ($directors as $director) {
            $service->send(
                recipients: $director,
                topic: RealtimeNotificationService::TOPIC_GENERAL,
                payload: [
                    'title' => __('notification.documentTemplatePendingApprovalTitle'),
                    'message' => $message,
                    'icon' => '📝',
                    'url' => '',
                    'action' => 'document_template_pending_approval',
                    'data' => [
                        'master_document_uid' => $this->document->uid,
                        'version' => $version->version,
                    ],
                ],
            );
        }

        // Notify developer
        $developer = \App\Models\User::where('email', config('app.developer_email'))
            ->first();

        if ($developer) {
            SlackNotificationJob::dispatch(
                previewMessage: 'New Document Template',
                message: $message,
                blockHeader: __('notification.documentTemplatePendingApprovalTitle'),
            );
        }

        $developer = \App\Models\User::where('email', config('app.developer_email'))->first();
        
        if ($developer) {
            // build block and content
            $block = (new SlackMessage)
                ->text('New Document Template')
                ->headerBlock(__('notification.documentTemplatePendingApprovalTitle'))
                ->sectionBlock(function (SectionBlock $block) use ($message) {
                    $block->text($message)->markdown();
                });
            $developer->notify(new SlackNotification($block));
        }
    }

    /**
     * The submitter is named whenever it can be resolved, since an approver's
     * first question is who sent the version over.
     */
    private function buildMessage(MasterDocumentFile $version): string
    {
        $replace = [
            'name' => $this->document->name,
            'version' => $version->version,
        ];

        $submitter = $version->author?->employee?->name ?? $version->author?->username;

        if (! $submitter) {
            return __('notification.documentTemplatePendingApprovalMessage', $replace);
        }

        return __('notification.documentTemplatePendingApprovalMessageWithSubmitter', $replace + ['submitter' => $submitter]);
    }
}
