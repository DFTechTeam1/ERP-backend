<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class RequestTeamMemberJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $projectId;

    private $data;

    /**
     * Create a new job instance.
     * 
     * @param int $projectId
     * @param array $data
     * 
     * $data will have
     * int transferId
     * int team
     * string pic_id
     */
    public function __construct(int $projectId, array $data)
    {
        $this->projectId = $projectId;

        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $project = \Modules\Production\Models\Project::selectRaw('id,project_date,name')
            ->find($this->projectId);

        $targetPic = \Modules\Hrd\Models\Employee::where('uid', $this->data['pic_id'])->first();

        if (!auth()->user()) {
            $projectPic = \Modules\Production\Models\ProjectPersonInCharge::selectRaw('id,pic_id')
                ->where('project_id', $this->projectId)
                ->latest()
                ->first();

            $employeeId = $projectPic->pic_id;
        } else {
            $employeeId = auth()->user()->employee_id;
        }

        $requestedBy = \Modules\Hrd\Models\Employee::find($employeeId);

        $player = \Modules\Hrd\Models\Employee::find($this->data['team']);

        $lineIds = [$targetPic->line_id];

        \Illuminate\Support\Facades\Notification::send($targetPic, new \Modules\Production\Notifications\RequestTeamMemberNotification($lineIds, $targetPic, $requestedBy, $player, $project, $this->data['transferId']));
    }
}
