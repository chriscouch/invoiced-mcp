<?php

namespace App\Core;

use Aws\Sdk;

class S3ProxyFactory
{
    public function __construct(
        private Sdk $aws,
        private string $environment,
        private string $bucketRegion)
    {
    }

    public function build(): mixed
    {
        return 'test' === $this->environment ? new NullFileProxy() : $this->aws->createS3(['region' => $this->bucketRegion]);
    }
}
