<?php

namespace Modules\Company\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class NasCreationSlackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly array $logData,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $developer = \App\Models\User::where('email', config('app.developer_email'))->first();

        if ($developer) {
            $developer->notify(new \Modules\Company\Notifications\NasCreationSlackNotification($this->logData));
        }
    }
}
