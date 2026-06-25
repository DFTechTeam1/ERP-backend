<?php

namespace Modules\Production\Jobs;

use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Email\Services\WhatsappService;
use Modules\Hrd\Repository\EmployeeRepository;

class RemoveUserFromTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  array<string>  $employeeUids
     */
    public function __construct(
        private readonly array $employeeUids,
        private readonly int $taskId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $task = \Modules\Production\Models\ProjectTask::selectRaw('id,project_id,name')
            ->with([
                'project:id,name,uid',
                'project.personInCharges:id,pic_id,project_id',
                'project.personInCharges.employee:id,uid,phone',
                'project.personInCharges.employee.picWhatsappGroups' => function ($query) {
                    $query->selectRaw('id,employee_id,group_id')
                        ->whereNotNull('community_id');
                }
            ])
            ->find($this->taskId);

        $whatsappService = new WhatsappService();
        $employeeRepo = new EmployeeRepository();

        if ($task?->project?->personIncharges->count() > 0) {
            foreach ($task->project->personIncharges as $pic) {
                if ($pic?->employee?->picWhatsappGroups->count() > 0) {
                    foreach ($pic?->employee?->picWhatsappGroups as $group) {

                        if ($group->community_id) {
                            // Loop removed employees
                            $employeeNames = [];
                            $mentions = [];
                            foreach ($this->employeeUids as $employeeUid) {
                                $employee = $employeeRepo->show(
                                    uid: $employeeUid, 
                                    select: 'id,nickname,phone',
                                    relation: [
                                        'whatsappGroups' => function ($queryGroup) use ($group) {
                                            $queryGroup->where('group_id', $group->group_id);
                                        }
                                    ]
                                );

                                if ($employee && $employee->whatsappGroups->count() > 0) {
                                    $employeeNames[] = $employee->nickname;
                                    $mentions[] = "62{$employee->phone}";
                                }
                            }
    
                            // Send message per employee
                            if (count($mentions) > 0) {
                                $payload = [
                                    'to' => $group->group_id,
                                    'message' => "Halo " . collect($employeeNames)->join(',') . ", task {$task->name} sudah tidak perlu dikerjakan lagi, kamu bisa fokus dengan task lain. Ganbate!",
                                    'isGroup' => true,
                                    'mentions' => $mentions,
                                    'actionType' => 'remove-pic-task',
                                ];
                        
                                $whatsappService->sendWhatsappMessage($payload);
                            }
                        }
                    }
                }
            }
        }
    }
}
