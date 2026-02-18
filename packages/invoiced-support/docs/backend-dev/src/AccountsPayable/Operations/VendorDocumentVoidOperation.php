<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Enums\PayableDocumentStatus;
use App\AccountsPayable\Ledger\AccountsPayableLedger;
use App\AccountsPayable\Models\PayableDocument;
use Carbon\CarbonImmutable;
use App\Core\Orm\Exception\ModelException;

/**
 * @template T of PayableDocument
 */
abstract class VendorDocumentVoidOperation
{
    public function __construct(
        protected AccountsPayableLedger $accountsPayableLedger,
    ) {
    }

    /**
     * Syncs the document with the ledger.
     *
     * @param T $document
     */
    abstract protected function ledgerSync(PayableDocument $document): void;

    /**
     * Voids the document. This operation is irreversible.
     *
     * @param T $document
     *
     * @throws ModelException
     */
    public function void(PayableDocument $document): void
    {
        if ($document->voided) {
            throw new ModelException('This document has already been voided.');
        }

        // void the document
        $document->voided = true;
        $document->date_voided = CarbonImmutable::now();
        $document->status = PayableDocumentStatus::Voided;
        $document->saveOrFail();

        // make the ledger entries
        $this->ledgerSync($document);
    }
}
