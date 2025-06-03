<?php

namespace Modules\Inventory\Jobs;

use App\Enums\Inventory\RequestInventoryStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Modules\Inventory\Models\RequestInventory;
use Modules\Inventory\Notifications\ProcessRequestInventoryNotification;

class ProcessRequestInventory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $uid;

    /**
     * Create a new job instance.
     */
    public function __construct(string $uid)
    {
        $this->uid = $uid;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $data = RequestInventory::with(['requester:id,email,name,nickname,telegram_chat_id', 'approvedByEmployee:id,nickname', 'rejectedByEmployee:id,nickname'])
            ->where('uid', $this->uid)->first();

        $message = "Halo {$data->requester->nickname}\n";
        if ($data->status == RequestInventoryStatus::Rejected->value) {
            $message .= 'permintaan barang '.$data->name.' kamu di tolak oleh '.$data->rejectedByEmployee->nickname;
        } elseif ($data->status == RequestInventoryStatus::Approved->value) {
            $message .= 'permintaan barang '.$data->name.' kamu disetujui dan segera diprocess oleh '.$data->approvedByEmployee->nickname;
        }

        Notification::send($data->requester, new ProcessRequestInventoryNotification($data->requester->telegram_chat_id, $message));
    }
}
