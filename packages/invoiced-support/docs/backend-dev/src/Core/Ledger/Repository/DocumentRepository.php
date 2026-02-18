<?php

namespace App\Core\Ledger\Repository;

use App\Core\Ledger\Enums\DocumentType;
use App\Core\Ledger\Exception\LedgerException;
use App\Core\Ledger\ValueObjects\Document;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

class DocumentRepository
{
    private DocumentTypeRepository $documentTypes;
    private array $documents = [];

    public function __construct(
        private Connection $database,
        private int $ledgerId,
    ) {
        $this->documentTypes = new DocumentTypeRepository($this->database);
    }

    /**
     * Gets the database ID of a document given its type and reference.
     */
    public function getId(DocumentType $type, string $reference): ?int
    {
        $hashKey = $this->documentHashKey($type, $reference);
        if (isset($this->documents[$hashKey])) {
            return $this->documents[$hashKey];
        }

        $documentId = $this->database->fetchOne('SELECT id FROM Documents WHERE ledger_id=:ledgerId AND document_type_id=:docType AND reference=:reference', [
            'ledgerId' => $this->ledgerId,
            'docType' => $this->documentTypes->getId($type),
            'reference' => $reference,
        ]);

        if (!$documentId) {
            return null;
        }

        // memoize the result because it will likely be referenced again
        $this->documents[$hashKey] = $documentId;

        return $documentId;
    }

    /**
     * Gets the database ID for a document given the value object.
     */
    public function getIdForDocument(Document $document): ?int
    {
        return $this->getId($document->type, $document->reference);
    }

    /**
     * Finds or creates a document given the value object.
     * NOTE: This function does not update the document
     * if it is existing.
     */
    public function getOrCreate(Document $document): int
    {
        if ($id = $this->getIdForDocument($document)) {
            return $id;
        }

        return $this->create($document);
    }

    /**
     * Creates a new document in the database.
     */
    public function create(Document $document): int
    {
        $this->database->insert('Documents', [
            'ledger_id' => $this->ledgerId,
            'document_type_id' => $this->documentTypes->getId($document->type),
            'reference' => $document->reference,
            'number' => $document->number,
            'party_type' => $document->party->type->value,
            'party_id' => $document->party->id,
            'date' => $document->date->toDateString(),
            'due_date' => $document->dueDate ? $document->dueDate->toDateString() : null,
            'created_at' => CarbonImmutable::now()->toDateTimeString(),
        ]);

        // memoize the result because it will likely be referenced again
        $documentId = (int) $this->database->lastInsertId();
        $this->documents[$this->documentHashKey($document->type, $document->reference)] = $documentId;

        return $documentId;
    }

    /**
     * Updates a document in the database.
     */
    public function update(int $documentId, Document $document): void
    {
        $this->database->update('Documents', [
            'document_type_id' => $this->documentTypes->getId($document->type),
            'reference' => $document->reference,
            'number' => $document->number,
            'party_type' => $document->party->type->value,
            'party_id' => $document->party->id,
            'date' => $document->date->toDateString(),
            'due_date' => $document->dueDate ? $document->dueDate->toDateString() : null,
        ], [
            'id' => $documentId,
        ]);
    }

    /**
     * Gets the database ID of a document type.
     *
     * @throws LedgerException
     */
    public function getDocumentTypeId(DocumentType $type): int
    {
        return $this->documentTypes->getId($type);
    }

    private function documentHashKey(DocumentType $type, string $reference): string
    {
        return $this->documentTypes->getId($type).$reference;
    }
}
