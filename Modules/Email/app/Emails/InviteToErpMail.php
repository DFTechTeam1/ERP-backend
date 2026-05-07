<?php

namespace Modules\Email\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Modules\Email\Data\Employees\User\InvitationEmail;

class InviteToErpMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public InvitationEmail $payload) {}

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->subject('Welcome to '.config('app.name').' — Your ERP Access')
            ->markdown('email::mail.hrd.employees.invite.invite-to-erp')
            ->with([
                'employeeName' => $this->payload->employeeName,
                'email' => $this->payload->email,
                'password' => $this->payload->password,
                'erpUrl' => $this->payload->erpUrl,
                'activationUrl' => $this->payload->activationUrl,
            ]);
    }
}
