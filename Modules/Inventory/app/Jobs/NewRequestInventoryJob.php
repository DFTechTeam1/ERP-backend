<?php

namespace Modules\Inventory\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Hrd\Models\Employee;

class NewRequestInventoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $data;

    private $target;

    private $requester;

    /**
     * Create a new job instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;

        $this->target = $data['target'];

        $this->requester = $data['requester'];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // get user will approval permission
        $lineIds = count($this->target) > 0 ?
            $this->formatTarget($this->target) :
            $this->getTargetByPermission();

        $requester = Employee::select('nickname')
            ->find($this->requester);

        $message = "Halo, ada permintaan pembelian barang baru dari {$requester->nickname} \n";
        foreach ($this->data as $key => $data) {
            $number = $key + 1;
            $message .= "{$number}. " . $data['name'] . "\n";
            if (count($data['purchase_lik']) > 0) {
//                $message .= "Referensi link pembelian: " . implode()
            }
        }

        $messages = [
            [
                'type' => 'text',
                'text' => "**Permintaan Barang**"
            ]
        ];
    }

    protected function formatTarget(array $data)
    {
        $output = [];
        foreach ($data as $employeeId) {
            $employee = Employee::selectRaw('line_id')
                ->find($employeeId);

            if ($employee->line_id) {
                $output[] = $employee->line_id;
            }
        }

        return $output;
    }

    protected function getTargetByPermission()
    {
        $users = User::permission('approve_request_inventory')
            ->with('employee:id,line_id')
            ->get();

        $lineIds = [];
        if (count($users) > 0) {
            $lineIds = collect($users)->pluck('employee.line_id')->filter(function ($line) {
                return $line != null || $line != '';
            })->values()->toArray();
        }

        return $lineIds;
    }
}
