<?php

namespace Modules\Production\Jobs;

use App\Services\GeneralService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

        $this->generalService = new GeneralService;

        // get the author information
        $employee = Employee::selectRaw('nickname,id')
            ->where('user_id', $authorId)
            ->first();

        $this->author = $employee->nickname;
    }

    /**
     * Execute the job.
     */
    public function handle(): void {
        $this->sendToWorker();
    }

    protected function sendToWorker()
    {
        $projectId = $this->generalService->getIdFromUid($this->projectUid, new Project);
        $songId = $this->generalService->getIdFromUid($this->songUid, new ProjectSongList);

        $task = EntertainmentTaskSong::selectRaw('id,project_song_list_id,employee_id,project_id')
            ->with([
                'employee:id,nickname,telegram_chat_id,user_id',
                'project:id,name,uid',
                'song:id,name',
            ])
            ->whereRaw("project_id = {$projectId} and project_song_list_id = {$songId}")
            ->first();

        $user = \App\Models\User::find($task->employee->user_id);

        \Illuminate\Support\Facades\Log::debug('Task Revise', $task->toArray());
        \Illuminate\Support\Facades\Log::debug('User Revise', $user->toArray());

        $message = "Your task for song '{$task->song->name}' in project '{$task->project->name}' has been revised by {$this->author}. Please check the revisions.";

        $user->notify(new SongReviseNotification($message, $task->project->uid));

        $pusher = new \App\Services\PusherNotification();
        $pusher->send('my-channel-'.$user->id, 'new-db-notification', [
            'update' => true,
            'st' => true, // stand for stand for
            'm' => 'Task has been revised', // stand for message
            't' => 'Task Update', // stand for title
        ]);
    }
}
