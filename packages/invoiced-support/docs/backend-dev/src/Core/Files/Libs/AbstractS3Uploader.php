<?php

namespace App\Core\Files\Libs;

use App\Core\Files\Exception\UploadException;
use App\Core\Files\Interfaces\FileCreatorInterface;
use App\Core\Files\Models\File;
use App\Core\Utils\InfuseUtility as Utility;
use Aws\S3\Exception\S3Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

abstract class AbstractS3Uploader implements LoggerAwareInterface
{
    use LoggerAwareTrait;


    public function __construct(
        private readonly FileCreatorInterface $s3FileCreator,
        private string $bucket,
        private string $projectDir,
    ) {
    }

    /**
     * Moves an uploaded file into the temporary directory.
     *
     * @param string $tmp uploaded temp filename
     *
     * @throws UploadException
     */
    public function moveUploadedFile(string $tmp): string
    {
        $tempDir = $this->projectDir.'/var/uploads';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0774);
        }

        $newFilename = $tempDir.'/'.strtolower(Utility::guid(false));

        if (!move_uploaded_file($tmp, $newFilename)) {
            throw new UploadException('Could not move uploaded file');
        }

        return $newFilename;
    }

    /**
     * Uploads the file to S3.
     *
     * @param string $tmpFilename path to locally stored file to upload
     * @param string $filename    original file name
     *
     * @throws UploadException
     */
    public function upload(string $tmpFilename, string $filename, ?int $tenantId = null): File
    {
        // validate file size
        if (!FileValidator::validateFileSize($tmpFilename)) {
            @unlink($tmpFilename);
            throw new UploadException('Uploaded file exceeds maximum allowed size');
        }

        // validate file strict
        if (!FileValidator::validateFileStrict($tmpFilename, $filename)) {
            @unlink($tmpFilename);
            throw new UploadException('Uploaded file type is not allowed (strict validation)');
        }

        // validate file signature
        if (!FileValidator::validateFileSignature($tmpFilename, $filename)) {
            @unlink($tmpFilename);
            throw new UploadException('File signature does not match the expected type');
        }

        // Generate a safe filename
        $safeName = FileValidator::sanitizeFilename($filename);

        if ($safeName === null) {
            @unlink($tmpFilename);
            throw new UploadException('Invalid file name or extension');
        }

        $key = $this->generateKey();

        try {
            $file = $this->s3FileCreator->create($this->bucket, $safeName, $tmpFilename, $key, [
                'Bucket' => $this->bucket,
                'Key' => $key,
                'ContentDisposition' => 'attachment; filename="'.$safeName.'"',
                'SourceFile' => $tmpFilename,
            ], $tenantId);
        } catch (S3Exception $e) {
            $this->logger->error('Unable to upload attached file', ['exception' => $e]);
            throw new UploadException('Unable to upload attached file');
        }

        // delete the temporary file
        @unlink($tmpFilename);

        return $file;
    }

    private function generateKey(): string
    {
        return strtolower(Utility::guid(false));
    }
}
