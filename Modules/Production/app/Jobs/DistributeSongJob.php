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
use Modules\Production\Models\ProjectSongList;
use Modules\Production\Notifications\DistributeSongNotification;

class DistributeSongJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $employeeUid;

    public $songUid;

    public $projectUid;

    /**
     * Create a new job instance.
     */
    public function __construct(string $employeeUid, string $projectUid, string $songUid)
    {
        $this->employeeUid = $employeeUid;
        $this->songUid = $songUid;
        $this->projectUid = $projectUid;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $employee = Employee::selectRaw('id,nickname,telegram_chat_id')
            ->where('uid', $this->employeeUid)
            ->first();
        $song = ProjectSongList::selectRaw('id,name')
            ->where('uid', $this->songUid)
            ->first();
        $project = Project::selectRaw('id,name')
            ->where('uid', $this->projectUid)
            ->first();

        if ($employee->telegram_chat_id) {
            $message = "Halo {$employee->nickname}\n";
            $message .= "Kamu ditugaskan untuk buat JB di event {$project->name}.\n";
            $message .= "Musik yang akan kamu kerjakan adalah {$song->name}";

            $user = User::where('employee_id', $employee->id)
                ->first();

            $user->notify(new DistributeSongNotification([$employee->telegram_chat_id], $message));
        }
    }
}
