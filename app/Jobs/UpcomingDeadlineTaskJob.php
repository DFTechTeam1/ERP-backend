<?php

namespace App\Jobs;

use App\Notifications\UpcomingDeadlineNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpcomingDeadlineTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $payload;

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->payload as $detail) {
            logging('test', $detail);
            $employee = $detail['employee']['employee'];

            $employee->notify(new UpcomingDeadlineNotification($detail));
        }
    }
}
