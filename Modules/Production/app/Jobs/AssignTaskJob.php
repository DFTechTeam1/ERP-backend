<?php

namespace Modules\Production\Jobs;

use App\Repository\UserRepository;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Email\Services\WhatsappService;
use Modules\Hrd\Repository\EmployeeRepository;

class AssignTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly array $employeeIds,
        private readonly int $taskId,
        private readonly object $userData,
        private readonly int $actorId
    ) {
    }

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
                    $query->selectRaw('id,employee_id,group_id,community_id')
                        ->whereNotNull('community_id');
                }
            ])
            ->find($this->taskId);

        $actor = (new UserRepository)->detail(id: $this->actorId, select: 'id,employee_id', relation: [
            'employee:id,nickname'
        ]);

        $whatsappService = new WhatsappService();
        $employeeRepo = new EmployeeRepository();

        logging("task remove pic", $task->toArray());

        if ($task?->project?->personIncharges->count() > 0) {
            foreach ($task->project->personIncharges as $pic) {
                if ($pic?->employee?->picWhatsappGroups->count() > 0) {
                    foreach ($pic?->employee?->picWhatsappGroups as $group) {

                        if ($group->community_id) {
                            // Loop new assigned employees
                            $employeeNames = [];
                            $mentions = [];
                            foreach ($this->employeeIds as $employeeId) {
                                $employee = $employeeRepo->show(
                                    uid: '', 
                                    where: "id = {$employeeId}",
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
                                    'message' => "Halo " . collect($employeeNames)->join(',') . ", ada tugas baru nih di event {$task->project->name} - *{$task->name}*. Login di ERP untuk memulai ya!",
                                    'isGroup' => true,
                                    'mentions' => $mentions,
                                    'actionType' => 'assign-pic-task',
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
