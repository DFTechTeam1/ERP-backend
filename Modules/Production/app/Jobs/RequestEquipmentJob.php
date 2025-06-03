<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class RequestEquipmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $project;

    /**
     * Create a new job instance.
     */
    public function __construct(object $project)
    {
        $this->project = $project;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $employees = getPicOfInventory();

        logging('request equipment employees: ', [$employees]);

        $project = \Modules\Production\Models\Project::selectRaw('id,project_date,name')
            ->with([
                'equipments:id,project_id,inventory_id,qty,created_by',
                'equipments.userCreated:id,employee_id',
                'equipments.userCreated.employee:id,name',
                'equipments.inventory:id,name',
            ])
            ->find($this->project->id);

        $equipments = $project->equipments;
        $messages = [];

        $messages[] = [
            'type' => 'text',
            'text' => 'Halo, ada permintaan equipment nih untuk event '.$project->name.' dari '.$project->equipments[0]->userCreated->employee->name.' dipakai tanggal '.date('d F Y', strtotime($project->project_date)).'. Berikut detail nya ya',
        ];

        $equipmentMessage = '';

        // chunk into 3
        $equipments = array_chunk(collect($equipments)->toArray(), 3);
        $newInventory = [];
        foreach ($equipments as $eKey => $chunk) {
            $messageInventory = '';
            foreach ($chunk as $equipment) {
                $messageInventory .= "Nama: {$equipment['inventory']['name']} \n";
                $messageInventory .= "Jumlah: {$equipment['qty']} \n";
            }

            $newInventory[$eKey] = ['type' => 'text', 'text' => $messageInventory];
        }

        $messages = array_merge($messages, $newInventory);

        Notification::send($employees, new \Modules\Production\Notifications\RequestEquipmentNotification($employees, $messages));
    }
}
