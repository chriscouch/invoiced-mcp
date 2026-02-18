<?php

namespace App\Core\Files\Libs;

use App\Core\Files\Interfaces\FileCreatorInterface;
use App\Core\Files\Models\File;

class NullFileCreator implements FileCreatorInterface
{
    public function create(
        string $bucket,
        string $fileName,
        mixed $originalFile,
        string $key,
        array $awsParameters,
        ?int $tenantId = null,
    ): File
    {
        return new File();
    }
}