<?php

namespace Modules\Production\Jobs;

use App\Services\GeneralService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Models\InteractiveProject;

class NotifyNewInteractiveProject implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Collection $interactive;

    /**
     * Create a new job instance.
     */
    public function __construct(Collection|InteractiveProject $interactive)
    {
        $this->interactive = $interactive;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // get interacive_pic from settings
        $interactivePic = (new GeneralService)->getSettingByKey('interactive_pic');

        if ($interactivePic) {
            $interactivePic = json_decode($interactivePic, true);

            $employees = (new EmployeeRepository)->list(
                select: 'id,name,email,telegram_chat_id',
                where: "uid IN ('".implode("','", $interactivePic)."')"
            );

            foreach ($employees as $employee) {

            }
        }
    }
}
