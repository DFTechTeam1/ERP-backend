<?php

namespace Modules\Company\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use Modules\Company\Notifications\SlackNotification;

class SlackNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly string $previewMessage,
        public readonly string $message,
        public readonly string $blockHeader,
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $developer = \App\Models\User::where('email', config('app.developer_email'))->first();
        if ($developer) {
            // build block and content
            $block = (new SlackMessage)
                ->text($this->previewMessage)
                ->headerBlock($this->blockHeader)
                ->sectionBlock(function (SectionBlock $block) {
                    $block->text($this->message)->markdown();
                });
            $developer->notify(new SlackNotification($block));
        }
    }
}
