<?php

namespace App\Companies\Libs;

use App\Companies\Models\Company;
use App\Core\S3ProxyFactory;
use Aws\S3\Exception\S3Exception;
use Aws\Sdk;
use Imagine\Exception\RuntimeException;
use Imagine\Image\ImageInterface;
use Imagine\Imagick\Imagine;
use App\Core\Utils\InfuseUtility as U;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class LogoUploader implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    const S3_PUBLIC_ENDPOINT = 'https://logos.invoiced.com/';
    private const LOGO_HEIGHT = 400;
    private const LOGO_WIDTH = 400;
    private const LOGO_EXT = 'png';

    private static array $allowedTypes = [
        'image/png',
        'image/jpeg',
        'image/gif',
    ];

    private mixed $s3;

    public function __construct(
        private string $bucket,
        private string $projectDir,
        string $bucketRegion,
        string $environment,
        Sdk $aws,
    )
    {
        $factory = new S3ProxyFactory($aws, $environment, $bucketRegion);
        $this->s3 = $factory->build();
    }

    /**
     * Moves an uploaded file into the temporary directory.
     *
     * @param string $tmp uploaded temp filename
     */
    public function moveUploadedFile(string $tmp): ?string
    {
        $tempDir = $this->projectDir.'/var/uploads';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0774);
        }

        $newFilename = $tempDir.'/'.strtolower(U::guid(false)).'.'.self::LOGO_EXT;

        if (!move_uploaded_file($tmp, $newFilename)) {
            return null;
        }

        return $newFilename;
    }

    /**
     * Changes the company's logo by resizing it, uploading to
     * S3, and.
     *
     * @param string $tmpFilename path to locally stored image to upload
     */
    public function upload(Company $company, string $tmpFilename): bool
    {
        // validate file type against a list of acceptable image types
        // by checking the "magic bytes"
        $type = mime_content_type($tmpFilename);
        if (!in_array($type, self::$allowedTypes)) {
            @unlink($tmpFilename);

            return false;
        }

        try {
            $imagine = new Imagine();

            // 1) Open image
            $image = $imagine->open($tmpFilename);

            // 2) Resize image
            $size = $image->getSize();
            if ($size->getHeight() > self::LOGO_HEIGHT) {
                $image->resize($size->heighten(self::LOGO_HEIGHT), ImageInterface::FILTER_LANCZOS);
            }

            $size = $image->getSize();
            if ($size->getWidth() > self::LOGO_WIDTH) {
                $image->resize($size->widen(self::LOGO_WIDTH), ImageInterface::FILTER_LANCZOS);
            }

            // 3) Save image
            $image->save($tmpFilename);
        } catch (RuntimeException $e) {
            return false;
        }

        // generate a unique object name for S3
        $new = strtolower(U::guid(false)).'.'.self::LOGO_EXT;

        try {
            // upload the file to s3
            $this->s3->putObject([
                'Bucket' => $this->bucket,
                'Key' => $new,
                'SourceFile' => $tmpFilename,
            ]);

            // delete the old picture (if exists)
            if ($company->logo) {
                $this->s3->deleteObject([
                    'Bucket' => $this->bucket,
                    'Key' => $company->logo,
                ]);
            }
        } catch (S3Exception $e) {
            $this->logger->error('Unable to upload logo', ['exception' => $e]);

            return false;
        }

        // delete the temporary picture
        @unlink($tmpFilename);

        // update the user properties
        $company->logo = $new;

        return $company->save();
    }
}
