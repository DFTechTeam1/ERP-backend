<?php

namespace App\Services;

use Modules\Email\Data\Notification\SendSlackMessageData;
use Modules\Email\Jobs\SendSlackJob;

class Slack
{
    public static function sendMessage(SendSlackMessageData $request)
    {
        SendSlackJob::dispatch($request);
    }
}
