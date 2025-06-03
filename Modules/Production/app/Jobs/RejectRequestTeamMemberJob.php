<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RejectRequestTeamMemberJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $transferUid;

    private $reason;

    /**
     * Create a new job instance.
     */
    public function __construct(string $transferUid, string $reason)
    {
        $this->transferUid = $transferUid;

        $this->reason = $reason;
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
            'alternativeEmployee:id,nickname',
        ])
            ->where('uid', $this->transferUid)
            ->first();

        $requested = \Modules\Hrd\Models\Employee::find($transfer->requested_by);
        $telegramChatIds = [$requested->telegram_chat_id];

        \Illuminate\Support\Facades\Notification::send($requested, new \Modules\Production\Notifications\RejectRequestTeamMemberNotification($telegramChatIds, $transfer));
    }
}
