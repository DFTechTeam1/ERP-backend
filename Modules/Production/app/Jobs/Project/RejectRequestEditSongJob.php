<?php

namespace Modules\Production\Jobs\Project;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectPersonInCharge;
use Modules\Production\Models\ProjectSongList;
use Modules\Production\Notifications\Project\RejectRequestEditSongNotification;

class RejectRequestEditSongJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $payload;

    private $projectUid;

    private $songUid;

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload, string $projectUid, string $songUid)
    {
        $this->payload = $payload;

        $this->projectUid = $projectUid;

        $this->songUid = $songUid;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $reason = $this->payload['reason'];

        // notify pm event
        $projectId = getIdFromUid($this->projectUid, new Project);
        $workers = ProjectPersonInCharge::where("project_id = {$projectId}")
            ->with('employee:id,nickname,email,telegram_chat_id')
            ->get();

        $song = ProjectSongList::selectRaw('id,name,project_id')
            ->where('uid', $this->songUid)
            ->first();

        $author = Employee::selectRaw('id,nickname')
            ->where('user_id', auth()->id())
            ->first();

        foreach ($workers as $worker) {
            if (($worker->employee) && ($worker->employee->telegram_chat_id)) {
                $message = "Halo {$worker->employee->nickname}\n";
                $message .= "{$author->nickname} menolak perubahaan di musik {$song->name} karna {$reason}";
                $worker->notify(new RejectRequestEditSongNotification([$worker->employee->telegram_chat_id], $message));
            }
        }
    }
}
