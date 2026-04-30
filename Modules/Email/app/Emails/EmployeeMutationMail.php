<?php

namespace Modules\Email\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Email\Data\Employees\Mutation\EmployeeData;

class EmployeeMutationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public EmployeeData $payload)
    {
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->markdown('email::mail.hrd.employees.mutation.employee')
            ->with([
                'employeeName' => $this->payload->employeeName,
                'oldPosition' => $this->payload->oldPosition,
                'newPosition' => $this->payload->newPosition,
                'department' => $this->payload->department,
                'effectiveDate' => $this->payload->effectiveDate,
            ]);
    }
}
