<?php

namespace Modules\Production\Jobs;

use App\Services\GeneralService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Notifications\RemindAssignmentMarcommNotification;
use Modules\Production\Repository\ProjectRepository;

class RemindAssignmentMarcomm implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     * This function is to notified marcomm PIC to assign their team to upcomming events
     * @return void
     */
    public function handle(): void
    {
        // get +7 days events where project do not have any marcomm attendance or marcomm afpat attendance assigned
        $projects = (new GeneralService)->getRemindIncomingProjects();

        // get marcom pic
        $marcommPic = (new GeneralService)->getSettingByKey(param: 'marcomm_pic');
        $marcommPic = '0370f81c-acd4-4d6a-98c1-f708fd483e0d';

        if ($marcommPic) {
            $marcommPic = (new EmployeeRepository)->show(uid: $marcommPic, select: 'id,email,telegram_chat_id,nickname,name');

            $marcommPic->notify(new RemindAssignmentMarcommNotification(
                projects: $projects,
                marcommPic: $marcommPic,
            ));
        }
    }
}
