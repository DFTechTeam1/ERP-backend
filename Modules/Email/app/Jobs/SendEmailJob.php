<?php

namespace Modules\Email\Jobs;

use App\Exceptions\NotFoundError;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use Modules\Email\Data\Notification\SendEmailData;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public SendEmailData $payload)
    {
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
        $mailable = $this->formatClass(mailable: $this->payload->emailType->getMailable());

        if (! $this->checkMailableClass(className: $mailable)) {
            throw new NotFoundError('Email template is not found');
        }

        setEmailConfiguration();
            
        Mail::to($this->payload->recipientEmail)
            ->send(new $mailable($this->payload->emailType->getTypeData($this->payload)));
    }
}
