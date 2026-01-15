<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Production\Notifications\RemovePicFromSongNotification;

class RemovePicFromSong implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $payload;

    /**
     * Create a new job instance.
     * @param \Modules\Production\Dto\Song\RemovePicNotificationDto $payload
     */
    public function __construct(\Modules\Production\Dto\Song\RemovePicNotificationDto $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $message = "You have been removed as PIC for the song '{$this->payload->songName}' in project '{$this->payload->projectName}'.";

        $user = \App\Models\User::find($this->payload->userId);
        $user->notify(new RemovePicFromSongNotification($message, $this->payload->projectUid));

        // Send to pusher notification
        $pusher = new \App\Services\PusherNotification();
        $pusher->send('my-channel-'.$this->payload->userId, 'new-db-notification', [
            'update' => true,
            'st' => true, // stand for stand for
            'm' => 'You have been removed as PIC from a song', // stand for message
            't' => 'Removed as PIC', // stand for title
        ]);
    }
}
