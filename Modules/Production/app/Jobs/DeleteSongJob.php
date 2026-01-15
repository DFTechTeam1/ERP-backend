<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Hrd\Models\Employee;
use Modules\Production\Notifications\DeleteSongNotification;

class DeleteSongJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $songName;

    public $requesterId;

    public $projectName;

    /**
     * Create a new job instance.
     */
    public function __construct(string $songName, string $projectName, int $requesterId)
    {
        $this->songName = $songName;
        $this->projectName = $projectName;
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
            $employee = Employee::selectRaw('id,nickname')
                ->where('user_id', $this->requesterId)
                ->first();

            $message = "{$employee->nickname} has been deleted the song {$this->songName} from project {$this->projectName}.";

            $entertainmentPic->notify(new DeleteSongNotification($message));

            $pusher = new \App\Services\PusherNotification();
            $pusher->send('my-channel-'.$entertainmentPic->id, 'new-db-notification', [
                'update' => true,
                'st' => true, // stand for stand for
                'm' => 'Song has been deleted by PM', // stand for message
                't' => 'Song Update', // stand for title
            ]);
        }
    }
}
