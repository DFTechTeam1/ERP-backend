<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectSongList;
use Modules\Production\Notifications\RequestSongNotification;

class RequestSongJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $project;

    public $songs;

    public $createdBy;

    /**
     * Create a new job instance.
     */
    public function __construct(\Modules\Production\Models\Project $project, array $songs, int $createdBy)
    {
        $this->project = $project;

        $this->songs = $songs;

        $this->createdBy = $createdBy;
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
            $requester = Employee::selectRaw('id,nickname,user_id')
                ->where('user_id', $this->createdBy)
                ->first();

            $numberOfSongs = count($this->songs);

            $message = "{$numberOfSongs} new song request has been made for project {$this->project->name} by {$requester->nickname}";

            $entertainmentPic->notify(new RequestSongNotification(
                telegramChatIds: [$entertainmentPic->employee->telegram_chat_id],
                message: $message
            ));

            // Send to pusher notification
            $pusher = new \App\Services\PusherNotification();
            $pusher->send('my-channel-'.$entertainmentPic->id, 'new-db-notification', [
                'update' => true,
                'st' => true, // stand for stand for
                'm' => 'You have new song request',
                't' => 'New Song Request',
            ]);
        }
    }
}
