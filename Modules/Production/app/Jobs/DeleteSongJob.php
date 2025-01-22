<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Hrd\Models\Employee;
use Modules\Production\Notifications\DeleteSongNotification;

class DeleteSongJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $song;

    public $requesterId;

    /**
     * Create a new job instance.
     */
    public function __construct(object $song, int $requesterId)
    {
        $this->song = $song;
        $this->requesterId = $requesterId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // get entertain pm
        $entertainmentPic = \App\Models\User::role('project manager entertainment')
            ->with('employee:id,nickname,user_id,telegram_chat_id')
            ->first();

        if (
            ($entertainmentPic) &&
            (
                ($entertainmentPic->employee) &&
                ($entertainmentPic->employee->telegram_chat_id)
            )
        ) {
            $employee = Employee::selectRaw('id,nickname')
                ->where('user_id', $this->requesterId)
                ->first();

            $message = "Halo {$entertainmentPic->employee->nickname}";
            $message .= "{$employee->nickname} telah menghapus musik " . $this->song->name . ' di event ' . $this->song->project->name;

            $telegramChatIds = [$entertainmentPic->employee->telegram_chat_id];

            $entertainmentPic->notify(new DeleteSongNotification($telegramChatIds, $message));
        }
    }
}
