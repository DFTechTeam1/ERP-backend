<?php

namespace Modules\Email\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Email\Data\BaseData;
use Modules\Email\Data\Notification\SendSlackMessageData;
use Modules\Email\Enums\SlackType;
use Modules\Email\Notifications\GlobalSlackNotification;

class SendSlackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public SendSlackMessageData $payload
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

        if ($developer) {;
            $developer->notify(new GlobalSlackNotification($this->payload));
        }

    }
}
