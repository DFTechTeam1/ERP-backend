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

    private $taskSong;

    /**
     * Create a new job instance.
     */
    public function __construct(object $taskSong)
    {
        $this->taskSong = $taskSong;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $message = "You have been removed as PIC for the song '{$this->taskSong->song->name}' in project '{$this->taskSong->project->name}'.";

        $this->taskSong->employee->notify(new RemovePicFromSongNotification($this->taskSong));

        // Send to pusher notification
        $pusher = new \App\Services\PusherNotification();
        $pusher->send('my-channel-'.$this->taskSong->employee->user_id, 'new-db-notification', [
            'update' => true,
            'st' => true, // stand for stand for
            'm' => 'You have been removed as PIC from a song', // stand for message
            't' => 'Removed as PIC', // stand for title
        ]);
    }
}
