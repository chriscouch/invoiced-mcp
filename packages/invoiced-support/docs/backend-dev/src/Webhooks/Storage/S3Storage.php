<?php

namespace App\Webhooks\Storage;

use App\Core\S3ProxyFactory;
use App\Core\Utils\Compression;
use App\Webhooks\Interfaces\PayloadStorageInterface;
use App\Webhooks\Models\WebhookAttempt;

class S3Storage implements PayloadStorageInterface
{
    private mixed $s3;

    public function __construct(
        private string $environment,
        private string $bucket,
        S3ProxyFactory $s3Factory,
    )
    {
        $this->s3 = $s3Factory->build();
    }

    public function store(WebhookAttempt $attempt, string $content): void
    {
        // NOTE: Exceptions are intentionally not caught here
        // in order to halt the webhook scheduling process.
        $this->s3->putObject([
            'Bucket' => $this->bucket,
            'Key' => $this->generateKey($attempt),
            'Body' => Compression::compress($content),
        ]);
    }

    public function retrieve(WebhookAttempt $attempt): ?string
    {
        // This is used for backwards compatibility
        if ($payload = $attempt->payload) {
            return $payload;
        }

        $key = $this->generateKey($attempt);
        if (!$this->s3->doesObjectExist($this->bucket, $key)) {
            return null;
        }

        // NOTE: Exceptions are intentionally not caught here
        // in order to halt the webhook emission process.
        $object = $this->s3->getObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);

        return Compression::decompressIfNeeded($object['Body']);
    }

    private function generateKey(WebhookAttempt $attempt): string
    {
        return $this->environment.'/'.$attempt->tenant_id.'/'.$attempt->id();
    }
}
