<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Notification;

class RequestEquipmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $project;

    /**
     * Create a new job instance.
     */
    public function __construct($project)
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

        $project = \Modules\Production\Models\Project::selectRaw('id,project_date')
            ->with([
                'equipments:id,project_id,inventory_id,qty,created_by',
                'equipments.userCreated:id,name',
                'equipments.inventory:id,name'
            ])
            ->find($this->project->id);

        $equipments = $project->equipments;
        logging('equipments', [$equipments]);
        $messages = [];

        $messages[] = [
            'type' => 'text',
            'text' => 'Halo, ada permintaan equipment nih untuk event '. $project->name .' dari '. $project->equipments[0]->userCreated->name .' dipakai tanggal ' . date('d F Y', strtotime($project->project_date)) . '. Berikut detail nya ya'
        ];

        $equipmentMessage = "";
        foreach ($equipments as $equipment) {
            $equipmentMessage .= "Nama: {$equipment->inventory->name} \n";
            $equipmentMessage .= "Jumlah: {$equipment->qty} \n";
        }

        $messages[] = [
            'type' => 'text',
            'text' => $equipmentMessage,
        ];

        $messages[] = [
            'type' => 'text',
            'text' => 'Untuk menyetujui permintaan ini, klik link berikut ya',
        ];

        Notification::send($employees, new \Modules\Production\Notifications\RequestEquipmentNotification($employees, $messages));
    }
}
