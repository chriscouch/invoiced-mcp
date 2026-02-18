<?php

namespace App\Integrations\ChartMogul\Syncs;

use App\AccountsReceivable\Models\ReceivableDocument;
use App\AccountsReceivable\Models\Invoice;

class InvoiceSync extends DocumentSync
{
    public static function getDefaultPriority(): int
    {
        return 10;
    }

    protected function getModelClass(): string
    {
        return Invoice::class;
    }

    /**
     * @param Invoice $document
     */
    public function getDatePaid(ReceivableDocument $document): int
    {
        return $document->date_paid ?? 0;
    }
}
