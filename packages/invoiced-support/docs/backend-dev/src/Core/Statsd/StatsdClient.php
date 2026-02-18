<?php

namespace App\Core\Statsd;

use DataDog\DogStatsd;

/**
 * @method increment(string $key, float $sampleRate = 1.0, array|string|null $tags = null)
 * @method gauge(string $key, float $value, float $sampleRate = 1.0, array|string|null $tags = null)
 * @method timing(string $key, float $time, float $sampleRate = 1.0, array|string|null $tags = null)
 * @method updateStats(string $key, int $delta = 1, float $sampleRate = 1.0, array|string|null $tags = null)
 */
class StatsdClient
{
    private DogStatsd $client;

    public function __construct(
        private string $host = '',
        private string $port = '',
        private string $namespace = ''
    ) {
        $this->host = trim($this->host);
    }

    /**
     * @return void
     */
    public function __call(string $name, array $arguments)
    {
        if (!$this->host) {
            return;
        }

        // forward api calls to the statsd client
        if (!isset($this->client)) {
            $this->client = new DogStatsd([
                'host' => $this->host,
                'port' => $this->port,
            ]);
        }

        // add a prefix to any metrics when there is a namespace
        if ($this->namespace) {
            $arguments[0] = $this->namespace.'.'.$arguments[0];
        }

        call_user_func_array([$this->client, $name], $arguments); /* @phpstan-ignore-line */
    }
}
