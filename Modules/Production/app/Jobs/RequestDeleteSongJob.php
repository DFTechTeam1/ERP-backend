<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Hrd\Models\Employee;
use Modules\Production\Notifications\RequestDeleteSongNotification;

class RequestDeleteSongJob implements ShouldQueue
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

        if ($entertainmentPic) {
            $requester = Employee::selectRaw('nickname')
                ->where('user_id', $this->requesterId)
                ->first();

            $message = "{$requester->nickname} has requested to delete the song {$this->song->name} from project {$this->song->project->name}.";

            $entertainmentPic->notify(new RequestDeleteSongNotification($message, $this->song->project->uid));

            $pusher = new \App\Services\PusherNotification();
            $pusher->send('my-channel-'.$entertainmentPic->id, 'new-db-notification', [
                'update' => true,
                'st' => true, // stand for stand for
                'm' => 'PM has requested to delete a song', // stand for message
                't' => 'Song Delete Request', // stand for title
            ]);
        }
    }
}
