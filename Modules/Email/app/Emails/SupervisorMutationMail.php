<?php

namespace Modules\Email\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Email\Data\Employees\Mutation\SupervisorData;

class SupervisorMutationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public SupervisorData $payload)
    {
        //
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->markdown('email::mail.hrd.employees.mutation.supervisor')
            ->with([
                'supervisorName' => $this->payload->supervisorName,
                'employeeName' => $this->payload->employeeName,
                'oldPosition' => $this->payload->oldPosition,
                'newPosition' => $this->payload->newPosition,
                'department' => $this->payload->department,
                'effectiveDate' => $this->payload->effectiveDate,
            ]);
    }
}
