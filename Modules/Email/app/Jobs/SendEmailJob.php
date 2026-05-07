<?php

namespace Modules\Email\Jobs;

use App\Exceptions\NotFoundError;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Modules\Email\Data\BaseData;
use Modules\Email\Enums\EmailType;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $recipientEmail,
        public EmailType $emailType,
        public BaseData $payload
    ) {
        //
    }

    protected function formatClass(string $mailable)
    {
        return "\\Modules\\Email\\Emails\\{$mailable}";
    }

    protected function checkMailableClass(string $className): bool
    {
        return class_exists($className);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check mailable class
        $mailable = $this->formatClass(mailable: $this->emailType->getMailable());

        if (! $this->checkMailableClass(className: $mailable)) {
            throw new NotFoundError('Email template is not found');
        }

        setEmailConfiguration();

        $dataMailable = $this->emailType->getTypeData($this->payload);

        if ($this->emailType === EmailType::InviteToErp) {
            $service = new \App\Services\EncryptionService;
            $encrypt = $service->encrypt($dataMailable->email, env('SALT_KEY'));
            $activationUrl = config('app.frontend_url').'/activate/'.$encrypt;

            $dataMailable->erpUrl = config('app.frontend_url');
            $dataMailable->activationUrl = $activationUrl;
        }

        Mail::to($this->recipientEmail)
            ->send(new $mailable($dataMailable));
    }
}
