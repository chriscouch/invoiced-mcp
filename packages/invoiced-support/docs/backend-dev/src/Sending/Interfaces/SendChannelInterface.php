<?php

namespace App\Sending\Interfaces;

use App\Sending\Models\ScheduledSend;

interface SendChannelInterface
{
    /**
     * Sends out a scheduled send.
     */
    public function send(ScheduledSend $scheduledSend): void;
}
