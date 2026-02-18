<?php

namespace App\Tests\Core\Ledger\Repository;

use App\Core\Ledger\Enums\DocumentType;
use App\Core\Ledger\Repository\LedgerRepository;
use App\Core\Ledger\ValueObjects\AccountingCustomer;
use App\Core\Ledger\ValueObjects\Document;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class DocumentRepositoryTest extends AppTestCase
{
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::getService('test.database')->executeQuery('SET foreign_key_checks = 0; DELETE FROM Ledgers WHERE name="Documents Test"; SET foreign_key_checks = 1;');
    }

    private function getLedgerRepository(): LedgerRepository
    {
        return new LedgerRepository(self::getService('test.database'));
    }

    public function testRepository(): void
    {
        $ledger = $this->getLedgerRepository()->findOrCreate('Documents Test', 'USD');

        $documents = $ledger->documents;
        $this->assertEquals(0, $documents->getId(DocumentType::Invoice, 'INV-00001'));

        $invoice = new Document(
            type: DocumentType::Invoice,
            reference: 'INV-00001',
            party: new AccountingCustomer(1234),
            date: CarbonImmutable::now(),
        );
        $documentId = $documents->create($invoice);
        $this->assertEquals($documentId, $documents->getId(DocumentType::Invoice, 'INV-00001'));
        $this->assertEquals($documentId, $documents->getIdForDocument($invoice));

        $updatedInvoice = new Document(
            type: DocumentType::Invoice,
            reference: 'INV-00001',
            party: new AccountingCustomer(1234),
            date: CarbonImmutable::now()->subDay(),
        );
        $documents->update($documentId, $updatedInvoice);
        $this->assertEquals($documentId, $documents->getIdForDocument($updatedInvoice));
    }
}
