<?php

namespace Modules\Production\Jobs;

use App\Services\GeneralService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Modules\Production\Notifications\SongReviseNotification;

class SongReviseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $payload;

    private $projectUid;

    private $songUid;

    private $generalService;

    private $author;

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload, string $projectUid, string $songUid, int $authorId)
    {
        $this->payload = $payload;

        $this->projectUid = $projectUid;

        $this->songUid = $songUid;

        $this->generalService = new GeneralService();

        // get the author information
        $employee = Employee::selectRaw('nickname,id')
            ->where('user_id', $authorId)
            ->first();
            
        $this->author = $employee->nickname;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        
    }

    protected function sendToWorker()
    {
        $projectId = $this->generalService->getIdFromUid($this->projectUid, new Project());
        $songId = $this->generalService->getIdFromUid($this->songUid, new ProjectSongList());

        $task = EntertainmentTaskSong::selectRaw('id,project_song_list_id,employee_id,project_id')
            ->with([
                'employee:id,nickname,telegram_chat_id',
                'project:id,name',
                'song:id,name'
            ])
            ->whereRaw("project_id = {$projectId} and project_song_list_id = {$songId}")
            ->first();

        if ($task->employee->telegram_chat_id) {
            $message = "Halo {$task->employee->nickname}\n";
            $message .= "JB musik {$task->song->name} di event {$task->project->name} di revisi oleh {$this->author}\n";
            $message .= "JB direvisi karna {$this->payload['reason']}\n";
            $message .= "Silahkan login untuk melihat detailnya dan memulai revisinya.";

            $task->employee->notify(new SongReviseNotification([$task->employee->telegram_chat_id], $message));
        }
    }
}
