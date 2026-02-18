<?php

namespace App\Core\Ledger\Repository;

use App\Core\Ledger\Enums\DocumentType;
use App\Core\Ledger\Exception\LedgerException;
use Doctrine\DBAL\Connection;

class DocumentTypeRepository
{
    private array $documentTypes;

    public function __construct(
        private Connection $database,
    ) {
    }

    /**
     * Creates a new document type in the database.
     */
    public function create(DocumentType $documentType): void
    {
        $this->database->executeStatement("INSERT INTO DocumentTypes (name) VALUES ('{$documentType->name}') ON DUPLICATE KEY UPDATE name=name");
    }

    /**
     * Gets the database ID of a document type.
     *
     * @throws LedgerException
     */
    public function getId(DocumentType $documentType): int
    {
        $this->getDocumentTypes();

        if (!isset($this->documentTypes[$documentType->name])) {
            throw new LedgerException('Document type does not exist: '.$documentType->name);
        }

        return $this->documentTypes[$documentType->name];
    }

    public function getDocumentTypes(): array
    {
        if (!isset($this->documentTypes)) {
            $types = $this->database->fetchAllAssociative('SELECT * FROM DocumentTypes');
            $this->documentTypes = [];
            foreach ($types as $type) {
                $this->documentTypes[$type['name']] = $type['id'];
            }
        }

        return $this->documentTypes;
    }
}
