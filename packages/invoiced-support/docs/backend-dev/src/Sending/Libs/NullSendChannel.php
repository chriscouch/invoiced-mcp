<?php

namespace App\Sending\Libs;

use App\Sending\Interfaces\SendChannelInterface;
use App\Sending\Models\ScheduledSend;

class NullSendChannel implements SendChannelInterface
{
    public function send(ScheduledSend $scheduledSend): void
    {
        // do nothing
    }
}
