<?php

namespace App\Core\Statsd\Interfaces;

use App\Core\Statsd\StatsdClient;

interface StatsdAwareInterface
{
    /**
     * Sets a statsd client.
     */
    public function setStatsd(StatsdClient $statsd): void;
}
