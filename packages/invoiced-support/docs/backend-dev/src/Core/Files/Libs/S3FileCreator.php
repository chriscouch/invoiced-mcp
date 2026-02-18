<?php

namespace App\Core\Files\Libs;

use App\Core\Files\Interfaces\FileCreatorInterface;
use App\Core\Files\Models\File;
use App\Core\S3ProxyFactory;

class S3FileCreator implements FileCreatorInterface
{
    private mixed $s3;

    public function __construct(
        private readonly string $bucketRegion,
        private readonly string $environment,
        private readonly string $filesUrl,
        S3ProxyFactory $s3Factory,
    ) {
        $this->s3 = $s3Factory->build();
    }

    public function create(
        string $bucket,
        string $fileName,
        string $originalFile,
        string $key,
        array $awsParameters,
        ?int $tenantId = null,
    ): File
    {
        $this->s3->putObject($awsParameters);

        $file = new File();
        if ($tenantId) {
            $file->tenant_id = $tenantId;
        }

        $file->name = $fileName;

        try {
            $file->size = (int) filesize($originalFile);
            $file->type = (string) mime_content_type($originalFile);
        } catch (\Exception) {
            $file->size = 0;
            $file->type = '/';
        }

        $file->url = $this->filesUrl . '/' . $key;
        $file->bucket_name = $bucket;
        $file->bucket_region = $this->bucketRegion;
        $file->s3_environment = $this->environment;
        $file->key = $key;
        $file->saveOrFail();

        return $file;
    }
}