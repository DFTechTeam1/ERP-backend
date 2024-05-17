<?php

namespace Modules\Hrd\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Notification;

class SendEmailActivationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $password;
    public $user;

    /**
     * Create a new job instance.
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // $this->user->notify(new \Modules\Hrd\Notifications\UserEmailActivation($this->password, $this->user));

        $service = new \App\Services\EncryptionService();
        $encrypt = $service->encrypt($this->user->email, env('SALT_KEY'));

        Notification::send($this->user, new \Modules\Hrd\Notifications\UserEmailActivation($this->user, $encrypt));
    }
}
