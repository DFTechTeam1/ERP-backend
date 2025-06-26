<?php

namespace Modules\Production\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Production\Models\ProjectDeal;

class PaymentDueReminder extends Mailable
{
    use Queueable, SerializesModels;

    public $projectDeal;

    /**
     * Create a new message instance.
     */
    public function __construct(ProjectDeal $projectDeal)
    {
        $this->projectDeal = $projectDeal;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->view('mail.payment.paymentDue');
    }
}
