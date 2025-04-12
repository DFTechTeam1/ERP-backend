<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
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
        $this->taskSong->employee->notify(new RemovePicFromSongNotification($this->taskSong));
    }
}
