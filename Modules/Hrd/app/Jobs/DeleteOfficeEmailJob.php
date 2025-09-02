<?php

namespace Modules\Hrd\Jobs;

use App\Enums\System\BaseRole;
use App\Repository\UserRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Hrd\Notifications\DeleteOfficeEmailNotification;
use Modules\Hrd\Repository\DeleteOfficeEmailQueueRepository;

class DeleteOfficeEmailJob implements ShouldQueue
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
     */
    public function handle(): void
    {
        $emails = (new DeleteOfficeEmailQueueRepository)->list(
            select: 'id,email',
            where: 'status = 0'
        );

        // get root users
        $rootUsers = (new UserRepository)->list(
            select: 'id,email,employee_id',
            whereRole: [BaseRole::Root->value],
            relation: [
                'employee:id,telegram_chat_id',
            ]
        );

        foreach ($rootUsers as $user) {
            $message = "Hello {$user->email}\n";
            $message .= "Please delete the following Office email:\n\n";
            foreach ($emails as $email) {
                $message .= "- {$email->email}\n";
            }
            // Send the email

            if ($user->employee->telegram_chat_id) {
                $user->notify(new DeleteOfficeEmailNotification($message, [$user->employee->telegram_chat_id]));
            }
        }
    }
}
