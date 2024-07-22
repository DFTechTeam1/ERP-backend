<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CancelRequestTeamMemberJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $uid;

    /**
     * Create a new job instance.
     */
    public function __construct($uid)
    {
        $this->uid = $uid;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $transfer = \Modules\Production\Models\TransferTeamMember::with([
            'employee:id,name,email',
            'requestToPerson:id,name,email',
            'requestByPerson:id,name,email',
        ])
            ->where('uid', $this->uid)->first();

        $targetPic = \Modules\Hrd\Models\Employee::find($transfer->request_to);

        $lineIds = [$targetPic->line_id];

        \Illuminate\Support\Facades\Notification::send($targetPic, new \Modules\Production\Notifications\CancelRequestTeamMemberNotification($transfer, $lineIds));
    }
}
