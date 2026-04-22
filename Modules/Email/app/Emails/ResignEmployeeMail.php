<?php

namespace Modules\Email\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResignEmployeeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $employeeName,
        public string $employeeId,
        public string $position,
        public string $department,
        public string $resignDate,
    ) {}

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->subject('Resignation Schedule Confirmation')
            ->markdown('email::mail.hrd.employees.resign.resign')
            ->with([
                'employeeName' => $this->employeeName,
                'employeeId' => $this->employeeId,
                'position' => $this->position,
                'department' => $this->department,
                'resignDate' => $this->resignDate,
            ]);
    }
}
