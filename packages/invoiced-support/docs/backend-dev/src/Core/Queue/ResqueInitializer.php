<?php

namespace App\Core\Queue;

use Redis;
use Resque;
use Resque_Redis;

class ResqueInitializer
{
    private bool $initialized = false;

    public function __construct(
        /** @var Redis */
        private $redisClient,
        private string $resqueNamespace,
        private ResqueEventBridge $resqueListener
    ) {
    }

    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        // Get the DSN for redis. This is needed because resque
        // will create a new redis instance when forking.
        $dsn = $this->redisClient->getHost().':'.$this->redisClient->getPort();
        Resque::setBackend($dsn);
        Resque::$redis = new Resque_Redis($dsn, 0, $this->redisClient);
        Resque_Redis::prefix($this->resqueNamespace);
        $this->resqueListener->register();
        $this->initialized = true;
    }
}
