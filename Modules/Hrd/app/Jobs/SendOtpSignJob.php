<?php

namespace Modules\Hrd\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Modules\Hrd\Notifications\OtpSignDocument;
use Modules\Hrd\Repository\EmployeeSignatureTaskRepository;

class SendOtpSignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const OTP_TTL_MINUTES = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly ?Authenticatable $user,
        private readonly int $employeeDocumentId,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->user === null) {
            return;
        }

        $repo = app(EmployeeSignatureTaskRepository::class);

        $task = $repo->show([
            'where' => [
                'employee_id' => $this->user->employee_id,
                'employee_document_id' => $this->employeeDocumentId,
            ],
        ]);

        if (! $task) {
            return;
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $repo->update($task, [
            'otp' => $otp,
            'otp_expired_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
        ]);

        Notification::send(
            $this->user,
            new OtpSignDocument($otp, self::OTP_TTL_MINUTES),
        );
    }
}
