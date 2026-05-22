<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class PartnerEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $to, 
        public string $subject, 
        public string $body
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        setEmailConfiguration();
        
        Mail::to($this->to)
            ->send(new \App\Mail\PartnerEmailNotification($this->subject, $this->body));
    }
}
