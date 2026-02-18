<?php
namespace App\Core\Files\Interfaces;

use App\Core\Files\Models\File;

interface FileCreatorInterface
{
    public function create(
        string $bucket,
        string $fileName,
        string $originalFile,
        string $key,
        array $awsParameters,
        ?int $tenantId = null,
    ): File;
}