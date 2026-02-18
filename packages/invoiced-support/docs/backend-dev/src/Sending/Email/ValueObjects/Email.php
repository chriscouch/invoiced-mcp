<?php

namespace App\Sending\Email\ValueObjects;

use App\Core\Files\Models\File;

/**
 * This class represents an email.
 */
class Email extends AbstractEmail
{
    /** @var File[] */
    private array $files = [];

    /**
     * @return File[]
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * @param File[] $files
     */
    public function setFiles(array $files): self
    {
        $this->files = $files;

        return $this;
    }
}
