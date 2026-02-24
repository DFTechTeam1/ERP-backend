<?php

namespace Modules\Production\Jobs\OnDuty;

use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Production\Repository\ProjectRepository;

class NotifyInchargeMarcom implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     * @param  array<string>  $newMarcommIds
     */
    public function __construct(
        private readonly array $newMarcommIds,
        private readonly string $projectUid
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $project = (new ProjectRepository)->show(
            uid: $this->projectUid,
            select: 'id,name,project_date'
        );

        foreach ($this->newMarcommIds as $marcomm) {
            $employee = \Modules\Hrd\Models\Employee::where('uid', $marcomm)->first();

            if ($employee) {
                NotificationService::send(
                    recipients: $employee,
                    action: 'notify_incharge_marcom',
                    data: [],
                    channels: ['email', 'database'],
                    options: [
                        // database options
                        'title' => ''
                    ]
                );
            }
        }
    }
}
