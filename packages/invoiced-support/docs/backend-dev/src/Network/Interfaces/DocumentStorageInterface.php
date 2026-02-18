<?php

namespace App\Network\Interfaces;

use App\Network\Exception\DocumentStorageException;
use App\Network\Models\NetworkDocument;

interface DocumentStorageInterface
{
    /**
     * Persists the document to the storage layer.
     *
     * @throws DocumentStorageException
     */
    public function persist(NetworkDocument $document, string $data): void;

    /**
     * Retrieves the latest version of a stored document.
     *
     * @throws DocumentStorageException
     */
    public function retrieve(NetworkDocument $document): string;

    /**
     * Retrieves a given version of a stored document.
     *
     * @throws DocumentStorageException
     */
    public function retrieveVersion(NetworkDocument $document, int $version): string;
}
