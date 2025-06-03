<?php

namespace Modules\Inventory\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Modules\Hrd\Models\Employee;
use Modules\Inventory\Notifications\NewRequestInventoryNotification;

class NewRequestInventoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $data;

    /**
     * Create a new job instance.
     */
    public function __construct(object $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $requester = Employee::select('nickname')
            ->find($this->data->requested_by);
        foreach ($this->data->approval_target as $target) {
            $employee = Employee::find($target);

            Notification::send($employee, new NewRequestInventoryNotification($this->data, $employee, $requester));
        }
    }
}
