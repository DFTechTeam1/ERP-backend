<?php

namespace Modules\Production\Jobs;

use App\Enums\System\BaseRole;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Modules\Production\Notifications\ConfirmDeleteSongNotification;

class ConfirmDeleteSongJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $currentSongName;

    public $currentWorkerId;

    public $projectUid;

    /**
     * Create a new job instance.
     */
    public function __construct($currentSongName, $currentWorkerId, $projectUid)
    {
        $this->currentSongName = $currentSongName;
        $this->currentWorkerId = $currentWorkerId;
        $this->projectUid = $projectUid;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $employee = Employee::find($this->currentWorkerId);
        $entertainmentPic = \App\Models\User::role(BaseRole::ProjectManagerEntertainment->value)
            ->with('employee:id,nickname,user_id,telegram_chat_id')
            ->first();

        if ($employee->telegram_chat_id && $entertainmentPic) {
            $project = Project::selectRaw('id,name')
                ->where('uid', $this->projectUid)
                ->first();

            $message = "Halo {$employee->nickname}\n";
            $message .= "{$entertainmentPic->employee->nickname} sudah menyetujui untuk menghapus lagu {$this->currentSongName} dari event {$project->name}.\n";
            $message .= "Kamu bisa memulai tugas yang lain.";
            
            $employee->notify(new ConfirmDeleteSongNotification([$employee->telegram_chat_id], $message));
        }
    }
}
