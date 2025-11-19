<?php

namespace Modules\Production\Jobs;

use App\Services\GeneralService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Hrd\Repository\EmployeeRepository;

class NotifyApprovalRecipientWhenInteractiveHasBeenDelete implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly array $interactiveInformation)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $name = $this->interactiveInformation['name'];
        $date = $this->interactiveInformation['date'];

        // get recipients
        $persons = json_decode((new GeneralService)->getSettingByKey('person_to_approve_interactive_event'), true);

        if ($persons) {
            $implodeString = "'".implode("','", $persons)."'";
            $employees = (new EmployeeRepository)->list(
                select: 'id,name,email',
                where: "uid IN ({$implodeString})"
            );

            $employees->each(function ($employee) use ($name, $date) {
                // send notification
                $employee->notify(
                    new \Modules\Production\Notifications\NotifyApprovalRecipientWhenInteractiveHasBeenDeleteNotification(
                        name: $name,
                        date: $date,
                        recipientName: $employee->name
                    )
                );
            });
        }
    }
}
