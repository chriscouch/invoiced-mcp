<?php

namespace App\Network\DocumentStorage;

use App\Network\Exception\DocumentStorageException;
use App\Network\Interfaces\DocumentStorageInterface;
use App\Network\Models\NetworkDocument;

class InMemoryStorage implements DocumentStorageInterface
{
    private array $documents = [];

    public function persist(NetworkDocument $document, string $data): void
    {
        $key = $document->id.'/'.$document->version;
        $this->documents[$key] = $data;
    }

    public function retrieve(NetworkDocument $document): string
    {
        return $this->retrieveVersion($document, $document->version);
    }

    public function retrieveVersion(NetworkDocument $document, int $version): string
    {
        $key = $document->id.'/'.$document->version;
        if (!isset($this->documents[$key])) {
            throw new DocumentStorageException('Could not retrieve document');
        }

        return $this->documents[$key];
    }
}
