<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ApproveRequestTeamMemberJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $uid;

    /**
     * Create a new job instance.
     */
    public function __construct(string $uid)
    {
        $this->uid = $uid;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $transfer = \Modules\Production\Models\TransferTeamMember::with([
                'employee:id,name,email,nickname',
                'requestToPerson:id,name,email,nickname',
                'requestByPerson:id,name,email,nickname',
            ])
            ->where('uid', $this->uid)->first();

        $requested = \Modules\Hrd\Models\Employee::find($transfer->requested_by);

        $lineIds = [$requested->line_id];

        \Illuminate\Support\Facades\Notification::send($requested, new \Modules\Production\Notifications\ApproveRequestTeamMemberNotification($transfer, $lineIds));
    }
}
