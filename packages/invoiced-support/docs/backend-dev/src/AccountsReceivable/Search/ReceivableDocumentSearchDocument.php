<?php

namespace App\AccountsReceivable\Search;

use App\Core\Search\Interfaces\SearchDocumentInterface;
use App\AccountsReceivable\Models\ReceivableDocument;

abstract class ReceivableDocumentSearchDocument implements SearchDocumentInterface
{
    protected ReceivableDocument $document;

    public function toSearchDocument(): array
    {
        return [
            'name' => $this->document->name,
            'currency' => $this->document->currency,
            'subtotal' => $this->document->subtotal,
            'total' => $this->document->total,
            'number' => $this->document->number,
            'purchase_order' => $this->document->purchase_order,
            'date' => $this->document->date,
            'status' => $this->document->status,
            'metadata' => (array) $this->document->metadata,
            '_customer' => $this->document->customer,
            'customer' => [
                'name' => $this->document->customer()->name,
            ],
        ];
    }
}
