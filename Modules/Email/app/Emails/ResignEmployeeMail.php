<?php

namespace Modules\Email\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Modules\Email\Data\Employees\Resign\EmployeeResign;

class ResignEmployeeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public EmployeeResign $payload
    ) {}

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->subject('Resignation Schedule Confirmation')
            ->markdown('email::mail.hrd.employees.resign.resign')
            ->with([
                'employeeName' => $this->payload->employeeName,
                'employeeId' => $this->payload->employeeId,
                'position' => $this->payload->position,
                'department' => $this->payload->department,
                'resignDate' => $this->payload->resignDate,
            ]);
    }
}
