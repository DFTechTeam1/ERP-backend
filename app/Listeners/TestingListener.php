<?php

namespace App\Listeners;

use App\Events\TestingEvent;

class TestingListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(TestingEvent $event): void
    {
        logging('event', [$event]);
    }
}
