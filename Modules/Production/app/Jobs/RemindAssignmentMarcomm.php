<?php

namespace Modules\Production\Jobs;

use App\Services\GeneralService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Hrd\Repository\EmployeeRepository;
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
        $projects = (new ProjectRepository)->show(
            uid: 'id',
            select: 'id',
            where: "DATE(project_date) > DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND DATE(project_date) < DATE_ADD(CURDATE(), INTERVAL 14 DAY) AND NOT EXISTS (SELECT 1 FROM project_marcomm_attendances WHERE project_marcomm_attendances.project_id = projects.id) AND NOT EXISTS (SELECT 1 FROM project_marcomm_afpat_attendances WHERE project_marcomm_afpat_attendances.project_id = projects.id)",
        );

        // get marcom pic
        $marcommPic = (new GeneralService)->getSettingByKey(param: 'marcomm_pic');

        if ($marcommPic) {
            $marcommPic = (new EmployeeRepository)->show(uid: $marcommPic, select: 'id,email,telegram_chat_id');
            foreach ($projects as $project) {
                
            }
        }
    }
}
