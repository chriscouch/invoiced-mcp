<?php

namespace App\Core\Statsd;

trait StatsdAwareTrait
{
    protected StatsdClient $statsd;

    public function setStatsd(StatsdClient $statsd): void
    {
        $this->statsd = $statsd;
    }
}
