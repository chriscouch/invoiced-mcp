<?php

namespace App\AccountsPayable\Ledger;

use App\AccountsPayable\Enums\ApAccounts;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\PayableDocument;
use App\AccountsPayable\Models\VendorCredit;
use App\Companies\Models\Company;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Ledger\Enums\DocumentType;
use App\Core\Ledger\Ledger;
use App\Core\Ledger\ValueObjects\AccountingVendor;
use App\Core\Ledger\ValueObjects\Document;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class BillBalanceGenerator
{
    /** @var Ledger[] */
    private array $ledgers = [];

    public function __construct(
        private AccountsPayableLedger $ledgerRepository,
    ) {
    }

    public function getBalance(PayableDocument $document): Money
    {
        $ledger = $this->getLedger($document->tenant());
        $documentId = $this->getDocumentId($ledger, $document);
        if (!$documentId) {
            return Money::zero($document->currency);
        }

        $amount = $ledger->reporting->getDocumentBalance($documentId, ApAccounts::AccountsPayable->value);

        return new Money($amount->getCurrency()->getCode(), (int) $amount->negative()->getAmount());
    }

    public function getTransactions(PayableDocument $document): array
    {
        $ledger = $this->getLedger($document->tenant());
        $documentId = $this->getDocumentId($ledger, $document);
        if (!$documentId) {
            return [];
        }
        $transactions = $ledger->reporting->getDocumentTransactions($documentId, ApAccounts::AccountsPayable->value);

        $result = [];
        foreach ($transactions as $row) {
            $result[] = [
                'document_type' => $row['document_type'],
                'reference' => $row['reference'],
                'amount' => Money::fromMoneyPhp($row['amount'])->toDecimal(),
                'currency' => $row['amount']->getCurrency()->getCode(),
                'date' => $row['transaction_date'],
            ];
        }

        return $result;
    }

    private function getLedger(Company $company): Ledger
    {
        if (!isset($this->ledgers[$company->id])) {
            $this->ledgers[$company->id] = $this->ledgerRepository->getLedger($company);
        }

        return $this->ledgers[$company->id];
    }

    private function getDocumentId(Ledger $ledger, PayableDocument $document): ?int
    {
        $documentType = match (get_class($document)) {
            Bill::class => DocumentType::Invoice,
            VendorCredit::class => DocumentType::CreditNote,
            default => throw new InvalidArgumentException('Unsupported document type'),
        };

        return $ledger->documents->getIdForDocument(new Document(
            type: $documentType,
            reference: (string) $document->id,
            // These do not matter because we are getting the document ID only
            party: new AccountingVendor($document->vendor_id),
            date: new CarbonImmutable($document->date),
        ));
    }
}
