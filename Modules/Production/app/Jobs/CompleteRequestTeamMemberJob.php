<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CompleteRequestTeamMemberJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $transferUid;

    /**
     * Create a new job instance.
     */
    public function __construct(string $transferUid)
    {
        $this->transferUid = $transferUid;
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
            ->where('uid', $this->transferUid)->first();

        $targetPic = \Modules\Hrd\Models\Employee::find($transfer->request_to);

        $telegramChatIds = [$targetPic->telegram_chat_id];

        \Illuminate\Support\Facades\Notification::send($targetPic, new \Modules\Production\Notifications\CompleteRequestTeamMemberNotification($telegramChatIds, $transfer));
    }
}
