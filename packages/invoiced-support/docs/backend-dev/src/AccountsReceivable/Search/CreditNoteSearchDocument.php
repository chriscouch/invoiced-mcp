<?php

namespace App\AccountsReceivable\Search;

use App\AccountsReceivable\Models\CreditNote;

/**
 * @property CreditNote $document
 */
class CreditNoteSearchDocument extends ReceivableDocumentSearchDocument
{
    public function __construct(CreditNote $creditNote)
    {
        $this->document = $creditNote;
    }

    public function toSearchDocument(): array
    {
        $document = parent::toSearchDocument();
        $document['balance'] = $this->document->balance;

        return $document;
    }
}
