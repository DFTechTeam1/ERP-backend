<?php

namespace Modules\Production\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Modules\Production\Notifications\SongApprovedToBeEditedNotification;

class SongApprovedToBeEditedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $currentSong;

    public $targetSong;

    public $currentWorkerId;

    public $projectId;

    /**
     * Create a new job instance.
     */
    public function __construct($currentSong, $targetSong, $currentWorkerId, $projectId)
    {
        $this->currentSong = $currentSong;
        $this->targetSong = $targetSong;
        $this->currentWorkerId = $currentWorkerId;
        $this->projectId = $projectId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // notify to worker
        $this->notifyWorker();

        // notify to PM
        $this->notifyPM();
    }

    protected function notifyWorker()
    {
        $worker = Employee::find($this->currentWorkerId);

        $message = "Halo {$worker->nickname}\n";
        $message .= "Request perubahan musik dari {$this->currentSong} ke {$this->targetSong} sudah disetujui.";

        if ($worker->telegram_chat_id) {
            $worker->notify(new SongApprovedToBeEditedNotification([$worker->telegram_chat_id], $message));
        }
    }

    protected function notifyPM()
    {
        $users = $this->getProjectPIC();

        foreach ($users as $user) {
            $message = " Halo {$user->nickname}\n";
            $message .= "Request perubahan musik dari {$this->currentSong} ke {$this->targetSong} sudah disetujui";

            if ($user->telegram_chat_id) {
                $user->nofify(new SongApprovedToBeEditedNotification([$user->telegram_chat_id], $message));
            }
        }
    }

    protected function getProjectPIC()
    {
        $project = Project::selectRaw('id,name,project_date')
            ->with([
                'personInCharges:id,project_id,pic_id',
                'personInCharges.employee:id,nickname,user_id,email,name,uid,telegram_chat_id'
            ])
            ->find($this->projectId);

        $projectManagers = collect($project->personInCharges)->pluck('employee')->toArray();

        return $projectManagers;
    }
}
