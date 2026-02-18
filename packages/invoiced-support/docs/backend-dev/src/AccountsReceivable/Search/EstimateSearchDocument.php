<?php

namespace App\AccountsReceivable\Search;

use App\AccountsReceivable\Models\Estimate;

/**
 * @property Estimate $document
 */
class EstimateSearchDocument extends ReceivableDocumentSearchDocument
{
    public function __construct(Estimate $estimate)
    {
        $this->document = $estimate;
    }

    public function toSearchDocument(): array
    {
        $document = parent::toSearchDocument();
        $document['deposit'] = $this->document->deposit;
        $document['expiration_date'] = $this->document->expiration_date;
        $document['payment_terms'] = $this->document->payment_terms;

        return $document;
    }
}
