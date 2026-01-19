<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Production\Models\ProjectSongList;

class RejectDeleteSongJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $songUid;

    private $actorId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $actorId, string $songUid)
    {
        $this->actorId = $actorId;
        $this->songUid = $songUid;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $actor = \App\Models\User::with([
                'employee:id,user_id,nickname'
            ])
            ->find($this->actorId);

        $song = ProjectSongList::selectRaw('id,name')
            ->where('uid', $this->songUid)
            ->first();

        $message = "{$actor->employee->nickname} has rejected the request to delete the song with UID {$this->songUid}.";
    }
}
