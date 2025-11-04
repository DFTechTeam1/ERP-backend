<?php

namespace Modules\Finance\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Finance\Notifications\InvoiceHasBeenDeletedNotification;

class InvoiceHasBeenDeletedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $parentNumber;

    private $projectName;

    private $user;

    /**
     * Create a new job instance.
     */
    public function __construct(string $parentNumber, string $projectName, object $user)
    {
        $this->parentNumber = $parentNumber;
        $this->projectName = $projectName;
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $actor = User::with('employee:id,user_id,name,email')
            ->find($this->user->id);

        $financeUsers = User::role(['finance'])
            ->with(['employee:id,user_id,name'])
            ->get();

        foreach ($financeUsers as $user) {
            $user->notify(new InvoiceHasBeenDeletedNotification($user, $actor, $this->parentNumber, $this->projectName));
        }
    }
}