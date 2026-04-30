<?php

namespace Modules\Email\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InviteToErpMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $employeeName,
        public string $email,
        public string $password,
        public string $erpUrl,
        public string $activationUrl,
    ) {}

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->subject('Welcome to '.config('app.name').' — Your ERP Access')
            ->markdown('email::mail.hrd.employees.invite.invite-to-erp')
            ->with([
                'employeeName' => $this->employeeName,
                'email' => $this->email,
                'password' => $this->password,
                'erpUrl' => $this->erpUrl,
                'activationUrl' => $this->activationUrl,
            ]);
    }
}
