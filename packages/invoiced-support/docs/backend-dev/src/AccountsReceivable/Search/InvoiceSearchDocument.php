<?php

namespace App\AccountsReceivable\Search;

use App\AccountsReceivable\Models\Invoice;

/**
 * @property Invoice $document
 */
class InvoiceSearchDocument extends ReceivableDocumentSearchDocument
{
    public function __construct(Invoice $invoice)
    {
        $this->document = $invoice;
    }

    public function toSearchDocument(): array
    {
        $document = parent::toSearchDocument();
        $document['payment_terms'] = $this->document->payment_terms;
        $document['due_date'] = $this->document->due_date;
        $document['balance'] = $this->document->balance;
        $document['autopay'] = $this->document->autopay;
        $document['attempt_count'] = $this->document->attempt_count;
        $document['next_payment_attempt'] = $this->document->next_payment_attempt;

        return $document;
    }
}
