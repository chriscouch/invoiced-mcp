<?php

namespace App\Sending\Email\Storage;

use App\Core\S3ProxyFactory;
use App\Core\Utils\Compression;
use App\Core\Utils\Exception\CompressionException;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Interfaces\EmailBodyStorageInterface;
use App\Sending\Email\Models\InboxEmail;
use Aws\S3\Exception\S3Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class S3Storage implements LoggerAwareInterface, EmailBodyStorageInterface
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

    /**
     * Uploads text content for an email.
     *
     * @throws SendEmailException
     */
    public function store(InboxEmail $email, string $bodyText, string $type): void
    {
        // generate a unique object name for S3
        $new = $this->generateKey($email, $type);

        // upload the file
        try {
            $this->s3->putObject([
                'Bucket' => $this->bucket,
                'Key' => $new,
                'Body' => Compression::compress($bodyText),
            ]);
        } catch (CompressionException|S3Exception $e) {
            $this->logger->error('Unable to upload email body', ['exception' => $e]);

            throw new SendEmailException('Unable to upload email body');
        }
    }

    private function generateKey(InboxEmail $email, string $type): string
    {
        return $this->environment.'/'.$email->tenant()->id().'/'.$email->id().'/'.$type;
    }

    public function retrieve(InboxEmail $email, string $type): ?string
    {
        // generate a unique object name for S3
        $key = $this->generateKey($email, $type);

        if (!$this->s3->doesObjectExist($this->bucket, $key)) {
            return null;
        }

        try {
            // load from S3
            $object = $this->s3->getObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            // parse JSON
            return Compression::decompressIfNeeded($object['Body']);
        } catch (CompressionException|S3Exception $e) {
            $this->logger->error('Unable to retrieve email body', ['exception' => $e]);

            return null;
        }
    }
}
