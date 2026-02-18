<?php

namespace App\Exports\Libs;

use App\Core\Files\Interfaces\FileCreatorInterface;
use App\Core\Utils\InfuseUtility as Utility;
use App\Exports\Models\Export;
use Aws\S3\Exception\S3Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class ExportStorage implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly FileCreatorInterface $s3FileCreator,
        private readonly string $bucket,
    ) {
    }

    /**
     * Persists data to S3 using a randomized filename.
     */
    public function persist(Export $export, string $filename, string $tmpFilename): string
    {
        $key = strtolower(Utility::guid());

        try {
            $file = $this->s3FileCreator->create($this->bucket, $filename, $tmpFilename, $key, [
                'Bucket' => $this->bucket,
                'Key' => $key,
                'SourceFile' => $tmpFilename,
                'ContentDisposition' => 'attachment; filename="'.$filename.'"',
            ]);

            return $file->url;
        } catch (S3Exception $e) {
            $this->logger->error('Could not upload export', ['exception' => $e]);

            $export->status = Export::FAILED;
            $export->save();
        }

        return '';
    }
}
