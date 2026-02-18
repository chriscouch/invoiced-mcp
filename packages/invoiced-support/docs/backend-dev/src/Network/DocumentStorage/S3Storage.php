<?php

namespace App\Network\DocumentStorage;

use App\Core\S3ProxyFactory;
use App\Core\Utils\Compression;
use App\Core\Utils\Exception\CompressionException;
use App\Network\Exception\DocumentStorageException;
use App\Network\Interfaces\DocumentStorageInterface;
use App\Network\Models\NetworkDocument;
use Aws\Exception\AwsException;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class S3Storage implements DocumentStorageInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private mixed $s3;

    public function __construct(
        private string $bucket,
        private string $environment,
        S3ProxyFactory $s3Factory,
    ) {
        $this->s3 = $s3Factory->build();
    }

    public function persist(NetworkDocument $document, string $data): void
    {
        try {
            $this->s3->putObject([
                'Bucket' => $this->bucket,
                'Key' => $this->getKey($document, $document->version),
                'Body' => Compression::compress($data),
                'StorageClass' => 'STANDARD_IA',
            ]);
        } catch (CompressionException|AwsException $e) {
            $this->logger->error('Could not persist document', ['exception' => $e]);

            throw new DocumentStorageException('Could not persist document', $e->getCode(), $e);
        }
    }

    public function retrieve(NetworkDocument $document): string
    {
        return $this->retrieveVersion($document, $document->version);
    }

    public function retrieveVersion(NetworkDocument $document, int $version): string
    {
        try {
            $result = $this->s3->getObject([
                'Bucket' => $this->bucket,
                'Key' => $this->getKey($document, $version),
            ]);

            return Compression::decompress((string) $result['Body']);
        } catch (CompressionException|AwsException $e) {
            $this->logger->error('Could not retrieve document', ['exception' => $e]);

            throw new DocumentStorageException('Could not retrieve document', $e->getCode(), $e);
        }
    }

    private function getKey(NetworkDocument $document, int $version): string
    {
        return $this->environment.'/'.CarbonImmutable::createFromTimestamp($document->created_at, new DateTimeZone('UTC'))->format('Y/m/j').'/'.$document->id.'/'.$version;
    }
}
