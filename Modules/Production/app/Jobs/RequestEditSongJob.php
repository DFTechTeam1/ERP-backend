<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Modules\Production\Notifications\RequestEditSongNotification;

class RequestEditSongJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $payload;

    public $projectUid;

    public $songUid;

    public $requesterId;

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload, string $projectUid, string $songUid, int $requesterId)
    {
        $this->payload = $payload;
        $this->projectUid = $projectUid;
        $this->songUid = $songUid;
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
            $currentSong = ProjectSongList::select('name')
                ->where('uid', $this->songUid)
                ->first();

            $project = Project::selectRaw('id,name')
                ->where('uid', $this->projectUid)
                ->first();

            $requesterData = Employee::selectRaw('nickname')
                ->where('user_id', $this->requesterId)
                ->first();

            $message = "Halo {$entertainmentPic->employee->nickname}\n";
            $message .= "{$requesterData->nickname} request untuk ubah lagu untuk event {$project->name} dari {$currentSong->name} jadi {$this->payload['name']}";
            $message .= "\nLogin untuk melihat detailnya.";

            $entertainmentPic->notify(new RequestEditSongNotification(
                [$entertainmentPic->employee->telegram_chat_id],
                $message
            ));
        }
    }
}
